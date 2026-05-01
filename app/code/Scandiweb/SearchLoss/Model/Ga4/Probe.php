<?php

namespace Scandiweb\SearchLoss\Model\Ga4;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Probe
{
    private const XML_PATH_ENABLED = 'searchloss/ga4/enabled';
    private const XML_PATH_PROPERTY_ID = 'searchloss/ga4/property_id';
    private const XML_PATH_CREDENTIALS_JSON = 'searchloss/ga4/credentials_json';
    private const XML_PATH_OAUTH_CLIENT_ID = 'searchloss/ga4/oauth_client_id';
    private const XML_PATH_OAUTH_CLIENT_SECRET = 'searchloss/ga4/oauth_client_secret';
    private const XML_PATH_OAUTH_REFRESH_TOKEN = 'searchloss/ga4/oauth_refresh_token';

    private const TOKEN_SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const API_BASE = 'https://analyticsdata.googleapis.com/v1beta';

    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {}

    public function execute(string $startDate = '28daysAgo', string $endDate = 'today'): array
    {
        $results = [];

        $enabled = $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
        $propertyId = $this->normalisePropertyId((string)$this->scopeConfig->getValue(self::XML_PATH_PROPERTY_ID));
        $credentialsJson = trim((string)$this->scopeConfig->getValue(self::XML_PATH_CREDENTIALS_JSON));
        $oauthClientId = trim((string)$this->scopeConfig->getValue(self::XML_PATH_OAUTH_CLIENT_ID));
        $oauthClientSecret = trim((string)$this->scopeConfig->getValue(self::XML_PATH_OAUTH_CLIENT_SECRET));
        $oauthRefreshToken = trim((string)$this->scopeConfig->getValue(self::XML_PATH_OAUTH_REFRESH_TOKEN));

        $results[] = $this->line(
            'GA4 sync enabled',
            $enabled ? 'PASS' : 'FAIL',
            $enabled ? 'GA4 sync is enabled in Magento config.' : 'GA4 sync is disabled in Magento config.'
        );

        if (!$enabled) {
            $results[] = $this->line('Recommended mode', 'FAIL', 'Disable low-engagement section until GA4 is configured.');
            return $results;
        }

        if ($propertyId === '') {
            $results[] = $this->line('GA4 property ID', 'FAIL', 'Missing GA4 property ID.');
            $results[] = $this->line('Recommended mode', 'FAIL', 'Disable low-engagement section until GA4 property ID is configured.');
            return $results;
        }

        $results[] = $this->line('GA4 property ID', 'PASS', 'Using property ID: ' . $propertyId);

        $hasOauthCredentials = $oauthClientId !== '' && $oauthClientSecret !== '' && $oauthRefreshToken !== '';

        if ($hasOauthCredentials) {
            $results[] = $this->line('OAuth credentials', 'PASS', 'OAuth client ID, client secret, and refresh token are configured.');

            try {
                $accessToken = $this->getOauthAccessToken($oauthClientId, $oauthClientSecret, $oauthRefreshToken);
                $results[] = $this->line('GA4 authentication', 'PASS', 'OAuth refresh token created an access token successfully.');
            } catch (\Throwable $exception) {
                $results[] = $this->line('GA4 authentication', 'FAIL', $exception->getMessage());
                $results[] = $this->line('Recommended mode', 'FAIL', 'Disable low-engagement section until OAuth authentication works.');
                return $results;
            }
        } else {
            try {
                $credentials = $this->parseCredentials($credentialsJson);
                $results[] = $this->line('Service account JSON', 'PASS', 'Credentials JSON contains client_email and private_key.');
            } catch (\Throwable $exception) {
                $results[] = $this->line('Service account JSON', 'FAIL', $exception->getMessage());
                $results[] = $this->line('Recommended mode', 'FAIL', 'Disable low-engagement section until GA4 credentials are configured.');
                return $results;
            }

            try {
                $accessToken = $this->getAccessToken($credentials);
                $results[] = $this->line('GA4 authentication', 'PASS', 'Service account access token created successfully.');
            } catch (\Throwable $exception) {
                $results[] = $this->line('GA4 authentication', 'FAIL', $exception->getMessage());
                $results[] = $this->line('Recommended mode', 'FAIL', 'Disable low-engagement section until authentication works.');
                return $results;
            }
        }

        $searchTermRows = 0;
        $urlSearchTermRows = 0;

        try {
            $searchReport = $this->runSearchTermReport($propertyId, $accessToken, $startDate, $endDate);
            $searchTermRows = $this->countUsableSearchTermRows($searchReport);

            if ($searchTermRows > 0) {
                $results[] = $this->line(
                    'Level 1: search terms',
                    'PASS',
                    'Found ' . $searchTermRows . ' usable GA4 search-term rows.',
                    $this->sampleSearchTerms($searchReport)
                );
            } else {
                $results[] = $this->line(
                    'Level 1: search terms',
                    'FAIL',
                    'GA4 connected, but no usable search-term rows were returned for the selected date range.'
                );
            }
        } catch (\Throwable $exception) {
            $results[] = $this->line('Level 1: search terms', 'FAIL', $exception->getMessage());
        }

        try {
            $searchUrlReports = $this->runMagentoSearchUrlReports($propertyId, $accessToken, $startDate, $endDate);
            $urlSearchTermRows = $this->countUsableSearchUrlRows($searchUrlReports);

            if ($urlSearchTermRows > 0) {
                $results[] = $this->line(
                    'Level 1B: Magento search URLs',
                    'PASS',
                    'Found ' . $urlSearchTermRows . ' usable search terms extracted from Magento search-result page URLs.',
                    $this->sampleMagentoSearchUrlTerms($searchUrlReports)
                );
            } else {
                $results[] = $this->line(
                    'Level 1B: Magento search URLs',
                    'WARN',
                    'GA4 connected, but no Magento search-result URLs with extractable q= parameters were returned for the selected date range.',
                    $this->describeMagentoSearchUrlReports($searchUrlReports)
                );
            }
        } catch (\Throwable $exception) {
            $results[] = $this->line('Level 1B: Magento search URLs', 'WARN', $exception->getMessage());
        }

        $level2Pass = false;

        try {
            $engagementReport = $this->runEngagementBySearchTermReport($propertyId, $accessToken, $startDate, $endDate);
            $totals = $this->getEngagementTotals($engagementReport);

            $hasProductViews = $totals['itemViewEvents'] > 0;
            $hasAddToCarts = $totals['addToCarts'] > 0;
            $hasTransactions = $totals['transactions'] > 0;
            $hasRevenue = $totals['purchaseRevenue'] > 0;

            $level2Pass = $hasProductViews || $hasAddToCarts || $hasTransactions || $hasRevenue;

            if ($level2Pass) {
                $results[] = $this->line(
                    'Level 2: engagement by search term',
                    'PASS',
                    'GA4 returned engagement/ecommerce metrics grouped by search term.',
                    [
                        'itemViewEvents' => (string)$totals['itemViewEvents'],
                        'addToCarts' => (string)$totals['addToCarts'],
                        'transactions' => (string)$totals['transactions'],
                        'purchaseRevenue' => (string)$totals['purchaseRevenue'],
                    ]
                );
            } else {
                $results[] = $this->line(
                    'Level 2: engagement by search term',
                    'WARN',
                    'GA4 query worked, but returned zero product/ecommerce engagement by search term. Level 2 may not be attributable with current GA4 tracking.',
                    [
                        'itemViewEvents' => '0',
                        'addToCarts' => '0',
                        'transactions' => '0',
                        'purchaseRevenue' => '0',
                    ]
                );
            }
        } catch (\Throwable $exception) {
            $results[] = $this->line('Level 2: engagement by search term', 'WARN', $exception->getMessage());
        }

        if ($searchTermRows <= 0 && $urlSearchTermRows <= 0) {
            $results[] = $this->line('Recommended mode', 'FAIL', 'Disable low-engagement section. GA4 search terms or Magento search-result URLs are not available yet.');
        } elseif ($level2Pass) {
            $results[] = $this->line('Recommended mode', 'PASS', 'Level 2 can stay: low-engagement search diagnostics appear supportable.');
        } else {
            $results[] = $this->line('Recommended mode', 'WARN', 'Use Level 1 only or hide low-engagement diagnostics until engagement attribution is proven.');
        }

        return $results;
    }

    private function normalisePropertyId(string $propertyId): string
    {
        $propertyId = trim($propertyId);
        $propertyId = preg_replace('#^properties/#', '', $propertyId);

        return trim((string)$propertyId);
    }

    private function parseCredentials(string $json): array
    {
        if ($json === '' || $json === '{}') {
            throw new \RuntimeException('Missing service account JSON. Current value is empty or placeholder JSON.');
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Service account JSON is not valid JSON.');
        }

        if (empty($data['client_email']) || empty($data['private_key'])) {
            throw new \RuntimeException('Service account JSON must include client_email and private_key.');
        }

        if (empty($data['token_uri'])) {
            $data['token_uri'] = self::TOKEN_URI;
        }

        return $data;
    }

    private function getOauthAccessToken(string $clientId, string $clientSecret, string $refreshToken): string
    {
        $response = $this->curl(
            'POST',
            self::TOKEN_URI,
            [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ])
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new \RuntimeException(
                'OAuth refresh-token request failed with HTTP ' . $response['status'] . ': ' . $response['body']
            );
        }

        $data = json_decode($response['body'], true);

        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('OAuth token response did not include access_token.');
        }

        return (string)$data['access_token'];
    }

    private function getAccessToken(array $credentials): string
    {
        $now = time();

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $claim = [
            'iss' => $credentials['client_email'],
            'scope' => self::TOKEN_SCOPE,
            'aud' => $credentials['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $unsignedJwt = $this->base64UrlEncode(json_encode($header))
            . '.'
            . $this->base64UrlEncode(json_encode($claim));

        $signature = '';

        $signed = openssl_sign(
            $unsignedJwt,
            $signature,
            $credentials['private_key'],
            OPENSSL_ALGO_SHA256
        );

        if (!$signed) {
            throw new \RuntimeException('Could not sign service account JWT. Check the private_key formatting.');
        }

        $jwt = $unsignedJwt . '.' . $this->base64UrlEncode($signature);

        $response = $this->curl(
            'POST',
            $credentials['token_uri'],
            [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new \RuntimeException(
                'Token request failed with HTTP ' . $response['status'] . ': ' . $response['body']
            );
        }

        $data = json_decode($response['body'], true);

        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('Token response did not include access_token.');
        }

        return (string)$data['access_token'];
    }

    private function runSearchTermReport(string $propertyId, string $accessToken, string $startDate, string $endDate): array
    {
        return $this->runReport($propertyId, $accessToken, [
            'dateRanges' => [
                [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ],
            ],
            'dimensions' => [
                ['name' => 'searchTerm'],
                ['name' => 'eventName'],
            ],
            'metrics' => [
                ['name' => 'eventCount'],
            ],
            'dimensionFilter' => [
                'filter' => [
                    'fieldName' => 'eventName',
                    'stringFilter' => [
                        'matchType' => 'EXACT',
                        'value' => 'view_search_results',
                    ],
                ],
            ],
            'orderBys' => [
                [
                    'metric' => [
                        'metricName' => 'eventCount',
                    ],
                    'desc' => true,
                ],
            ],
            'limit' => 10,
        ]);
    }

    private function runMagentoSearchUrlReports(string $propertyId, string $accessToken, string $startDate, string $endDate): array
    {
        $dimensions = [
            'pageLocation',
            'fullPageUrl',
            'pagePathPlusQueryString',
            'pageTitle',
        ];

        $reports = [];
        $errors = [];

        foreach ($dimensions as $dimension) {
            try {
                $reports[$dimension] = $this->runReport($propertyId, $accessToken, [
                    'dateRanges' => [
                        [
                            'startDate' => $startDate,
                            'endDate' => $endDate,
                        ],
                    ],
                    'dimensions' => [
                        ['name' => $dimension],
                    ],
                    'metrics' => [
                        ['name' => 'eventCount'],
                    ],
                    'dimensionFilter' => [
                        'filter' => [
                            'fieldName' => $dimension,
                            'stringFilter' => [
                                'matchType' => 'CONTAINS',
                                'value' => '/catalogsearch/result/',
                            ],
                        ],
                    ],
                    'orderBys' => [
                        [
                            'metric' => [
                                'metricName' => 'eventCount',
                            ],
                            'desc' => true,
                        ],
                    ],
                    'limit' => 25,
                ]);
            } catch (\Throwable $exception) {
                $errors[$dimension] = $exception->getMessage();
            }
        }

        return [
            'reports' => $reports,
            'errors' => $errors,
        ];
    }

    private function countUsableSearchUrlRows(array $searchUrlReports): int
    {
        return count($this->getMagentoSearchTermsFromUrlReports($searchUrlReports));
    }

    private function sampleMagentoSearchUrlTerms(array $searchUrlReports): array
    {
        $sample = [];

        foreach (array_slice($this->getMagentoSearchTermsFromUrlReports($searchUrlReports), 0, 5, true) as $term => $count) {
            $sample[$term] = $count . ' search page views';
        }

        return $sample;
    }

    private function describeMagentoSearchUrlReports(array $searchUrlReports): array
    {
        $details = [];

        foreach (($searchUrlReports['reports'] ?? []) as $dimension => $report) {
            $rows = $report['rows'] ?? [];
            $terms = [];

            foreach ($rows as $row) {
                $url = trim((string)($row['dimensionValues'][0]['value'] ?? ''));
                $term = $this->extractMagentoSearchTermFromUrl($url);

                if ($term !== null) {
                    $terms[$term] = true;
                }
            }

            $details[$dimension] = count($rows) . ' rows, ' . count($terms) . ' q= terms extracted';
        }

        foreach (($searchUrlReports['errors'] ?? []) as $dimension => $error) {
            $message = (string)$error;

            if (strlen($message) > 180) {
                $message = substr($message, 0, 180) . '...';
            }

            $details[$dimension] = 'ERROR: ' . $message;
        }

        return $details;
    }

    private function getMagentoSearchTermsFromUrlReports(array $searchUrlReports): array
    {
        $terms = [];

        foreach (($searchUrlReports['reports'] ?? []) as $report) {
            foreach (($report['rows'] ?? []) as $row) {
                $url = trim((string)($row['dimensionValues'][0]['value'] ?? ''));
                $term = $this->extractMagentoSearchTermFromUrl($url);

                if ($term === null) {
                    continue;
                }

                $count = (float)($row['metricValues'][0]['value'] ?? 0);

                if (!isset($terms[$term]) || $count > $terms[$term]) {
                    $terms[$term] = $count;
                }
            }
        }

        arsort($terms);

        return $terms;
    }

    private function extractMagentoSearchTermFromUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));

        if ($url === '' || strtolower($url) === '(not set)') {
            return null;
        }

        if (stripos($url, 'catalogsearch/result') === false) {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        if ($query === null || $query === false || $query === '') {
            $questionMarkPosition = strpos($url, '?');

            if ($questionMarkPosition !== false) {
                $query = substr($url, $questionMarkPosition + 1);
            }
        }

        if (!is_string($query) || trim($query) === '') {
            return null;
        }

        $params = [];
        parse_str($query, $params);

        foreach (['q', 'query', 'search', 'keyword'] as $key) {
            if (!isset($params[$key])) {
                continue;
            }

            $term = trim((string)$params[$key]);
            $term = preg_replace('/\s+/', ' ', $term);

            if ($term !== '' && strtolower($term) !== '(not set)') {
                return $term;
            }
        }

        return null;
    }

    private function runEngagementBySearchTermReport(string $propertyId, string $accessToken, string $startDate, string $endDate): array
    {
        return $this->runReport($propertyId, $accessToken, [
            'dateRanges' => [
                [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ],
            ],
            'dimensions' => [
                ['name' => 'searchTerm'],
            ],
            'metrics' => [
                ['name' => 'eventCount'],
                ['name' => 'itemViewEvents'],
                ['name' => 'addToCarts'],
                ['name' => 'transactions'],
                ['name' => 'purchaseRevenue'],
            ],
            'orderBys' => [
                [
                    'metric' => [
                        'metricName' => 'eventCount',
                    ],
                    'desc' => true,
                ],
            ],
            'limit' => 10,
        ]);
    }

    private function runReport(string $propertyId, string $accessToken, array $payload): array
    {
        $url = self::API_BASE . '/properties/' . rawurlencode($propertyId) . ':runReport';

        $response = $this->curl(
            'POST',
            $url,
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            json_encode($payload)
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new \RuntimeException(
                'GA4 runReport failed with HTTP ' . $response['status'] . ': ' . $response['body']
            );
        }

        $data = json_decode($response['body'], true);

        if (!is_array($data)) {
            throw new \RuntimeException('GA4 runReport response was not valid JSON.');
        }

        return $data;
    }

    private function curl(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);

        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new \RuntimeException('cURL request failed: ' . $error);
        }

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => (string)$responseBody,
        ];
    }

    private function countUsableSearchTermRows(array $report): int
    {
        $count = 0;

        foreach (($report['rows'] ?? []) as $row) {
            $term = trim((string)($row['dimensionValues'][0]['value'] ?? ''));

            if ($term !== '' && strtolower($term) !== '(not set)') {
                $count++;
            }
        }

        return $count;
    }

    private function sampleSearchTerms(array $report): array
    {
        $sample = [];

        foreach (($report['rows'] ?? []) as $row) {
            $term = trim((string)($row['dimensionValues'][0]['value'] ?? ''));
            $count = (string)($row['metricValues'][0]['value'] ?? '0');

            if ($term !== '' && strtolower($term) !== '(not set)') {
                $sample[$term] = $count . ' searches/events';
            }
        }

        return array_slice($sample, 0, 5, true);
    }

    private function getEngagementTotals(array $report): array
    {
        $totals = [
            'eventCount' => 0.0,
            'itemViewEvents' => 0.0,
            'addToCarts' => 0.0,
            'transactions' => 0.0,
            'purchaseRevenue' => 0.0,
        ];

        $metricNames = [];

        foreach (($report['metricHeaders'] ?? []) as $index => $header) {
            $metricNames[$index] = (string)($header['name'] ?? '');
        }

        foreach (($report['rows'] ?? []) as $row) {
            foreach (($row['metricValues'] ?? []) as $index => $metricValue) {
                $name = $metricNames[$index] ?? '';

                if (isset($totals[$name])) {
                    $totals[$name] += (float)($metricValue['value'] ?? 0);
                }
            }
        }

        return $totals;
    }

    private function base64UrlEncode(string|false $value): string
    {
        if ($value === false) {
            $value = '';
        }

        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function line(string $check, string $status, string $message, array $details = []): array
    {
        return [
            'check' => $check,
            'status' => $status,
            'message' => $message,
            'details' => $details,
        ];
    }
}
