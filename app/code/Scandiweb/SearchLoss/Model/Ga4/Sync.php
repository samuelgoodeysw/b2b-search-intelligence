<?php

namespace Scandiweb\SearchLoss\Model\Ga4;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;

class Sync
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
        private ResourceConnection $resource,
        private ScopeConfigInterface $scopeConfig
    ) {}

    public function execute(string $startDate, string $endDate): int
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED)) {
            throw new \RuntimeException(
                'GA4 sync is disabled. Enable it in Stores > Configuration > Scandiweb > Search Loss Audit > GA4 Integration.'
            );
        }

        $propertyId = $this->normalisePropertyId((string)$this->scopeConfig->getValue(self::XML_PATH_PROPERTY_ID));

        if ($propertyId === '' || strtolower($propertyId) === 'test') {
            throw new \RuntimeException(
                'GA4 property ID is missing or still set to a placeholder. Configure a real GA4 property ID before syncing.'
            );
        }

        $accessToken = $this->getConfiguredAccessToken();

        $rowsByDateAndTerm = [];

        try {
            $this->mergeSearchTermRows(
                $rowsByDateAndTerm,
                $this->runSearchTermReport($propertyId, $accessToken, $startDate, $endDate)
            );
        } catch (\Throwable $exception) {
            // Keep going: URL extraction can still provide Level 1 search volume.
        }

        $searchUrlReports = $this->runMagentoSearchUrlReports($propertyId, $accessToken, $startDate, $endDate);
        $this->mergeSearchUrlRows($rowsByDateAndTerm, $searchUrlReports);

        try {
            $this->mergeEngagementRows(
                $rowsByDateAndTerm,
                $this->runEngagementBySearchTermReport($propertyId, $accessToken, $startDate, $endDate)
            );
        } catch (\Throwable $exception) {
            // Level 2 is useful, but Level 1 search volume should still sync if available.
        }

        if (!$rowsByDateAndTerm) {
            throw new \RuntimeException(
                'GA4 returned no usable Search Loss rows. Run bin/magento searchloss:ga4:probe first and confirm Level 1 or Level 1B passes.'
            );
        }

        $rows = array_values($rowsByDateAndTerm);

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('scandiweb_searchloss_ga4_term');

        $connection->insertOnDuplicate(
            $table,
            $rows,
            [
                'searches',
                'product_views',
                'add_to_carts',
                'purchases',
                'revenue',
            ]
        );

        return count($rows);
    }

    private function getConfiguredAccessToken(): string
    {
        $oauthClientId = trim((string)$this->scopeConfig->getValue(self::XML_PATH_OAUTH_CLIENT_ID));
        $oauthClientSecret = trim((string)$this->scopeConfig->getValue(self::XML_PATH_OAUTH_CLIENT_SECRET));
        $oauthRefreshToken = trim((string)$this->scopeConfig->getValue(self::XML_PATH_OAUTH_REFRESH_TOKEN));

        if ($oauthClientId !== '' && $oauthClientSecret !== '' && $oauthRefreshToken !== '') {
            return $this->getOauthAccessToken($oauthClientId, $oauthClientSecret, $oauthRefreshToken);
        }

        $credentialsJson = trim((string)$this->scopeConfig->getValue(self::XML_PATH_CREDENTIALS_JSON));
        $credentials = $this->parseCredentials($credentialsJson);

        return $this->getServiceAccountAccessToken($credentials);
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
                ['name' => 'date'],
                ['name' => 'searchTerm'],
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
            'limit' => 1000,
        ]);
    }

    private function runMagentoSearchUrlReports(string $propertyId, string $accessToken, string $startDate, string $endDate): array
    {
        $dimensions = [
            'pageLocation',
            'fullPageUrl',
            'pagePathPlusQueryString',
        ];

        $reports = [];

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
                        ['name' => 'date'],
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
                    'limit' => 1000,
                ]);
            } catch (\Throwable $exception) {
                // Some GA4 properties may not support every URL dimension.
            }
        }

        return $reports;
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
                ['name' => 'date'],
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
            'limit' => 1000,
        ]);
    }

    private function mergeSearchTermRows(array &$rowsByDateAndTerm, array $report): void
    {
        foreach (($report['rows'] ?? []) as $row) {
            $date = $this->normaliseGa4Date((string)($row['dimensionValues'][0]['value'] ?? ''));
            $term = $this->normaliseSearchTerm((string)($row['dimensionValues'][1]['value'] ?? ''));

            if ($date === null || $term === null) {
                continue;
            }

            $key = $this->rowKey($date, $term);
            $count = (int)round((float)($row['metricValues'][0]['value'] ?? 0));

            $this->ensureRow($rowsByDateAndTerm, $date, $term);
            $rowsByDateAndTerm[$key]['searches'] = max($rowsByDateAndTerm[$key]['searches'], $count);
        }
    }

    private function mergeSearchUrlRows(array &$rowsByDateAndTerm, array $reports): void
    {
        foreach ($reports as $report) {
            foreach (($report['rows'] ?? []) as $row) {
                $date = $this->normaliseGa4Date((string)($row['dimensionValues'][0]['value'] ?? ''));
                $url = (string)($row['dimensionValues'][1]['value'] ?? '');
                $term = $this->extractMagentoSearchTermFromUrl($url);

                if ($date === null || $term === null) {
                    continue;
                }

                $key = $this->rowKey($date, $term);
                $count = (int)round((float)($row['metricValues'][0]['value'] ?? 0));

                $this->ensureRow($rowsByDateAndTerm, $date, $term);
                $rowsByDateAndTerm[$key]['searches'] = max($rowsByDateAndTerm[$key]['searches'], $count);
            }
        }
    }

    private function mergeEngagementRows(array &$rowsByDateAndTerm, array $report): void
    {
        $metricNames = [];

        foreach (($report['metricHeaders'] ?? []) as $index => $header) {
            $metricNames[$index] = (string)($header['name'] ?? '');
        }

        foreach (($report['rows'] ?? []) as $row) {
            $date = $this->normaliseGa4Date((string)($row['dimensionValues'][0]['value'] ?? ''));
            $term = $this->normaliseSearchTerm((string)($row['dimensionValues'][1]['value'] ?? ''));

            if ($date === null || $term === null) {
                continue;
            }

            $key = $this->rowKey($date, $term);
            $this->ensureRow($rowsByDateAndTerm, $date, $term);

            foreach (($row['metricValues'] ?? []) as $index => $metricValue) {
                $metricName = $metricNames[$index] ?? '';
                $value = (float)($metricValue['value'] ?? 0);

                if ($metricName === 'eventCount') {
                    $rowsByDateAndTerm[$key]['searches'] = max($rowsByDateAndTerm[$key]['searches'], (int)round($value));
                } elseif ($metricName === 'itemViewEvents') {
                    $rowsByDateAndTerm[$key]['product_views'] = (int)round($value);
                } elseif ($metricName === 'addToCarts') {
                    $rowsByDateAndTerm[$key]['add_to_carts'] = (int)round($value);
                } elseif ($metricName === 'transactions') {
                    $rowsByDateAndTerm[$key]['purchases'] = (int)round($value);
                } elseif ($metricName === 'purchaseRevenue') {
                    $rowsByDateAndTerm[$key]['revenue'] = round($value, 4);
                }
            }
        }
    }

    private function ensureRow(array &$rowsByDateAndTerm, string $date, string $term): void
    {
        $key = $this->rowKey($date, $term);

        if (isset($rowsByDateAndTerm[$key])) {
            return;
        }

        $rowsByDateAndTerm[$key] = [
            'report_date' => $date,
            'search_term' => $term,
            'searches' => 0,
            'product_views' => 0,
            'add_to_carts' => 0,
            'purchases' => 0,
            'revenue' => 0.0000,
        ];
    }

    private function rowKey(string $date, string $term): string
    {
        return $date . '::' . mb_strtolower($term);
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

            return $this->normaliseSearchTerm((string)$params[$key]);
        }

        return null;
    }

    private function normaliseSearchTerm(string $term): ?string
    {
        $term = trim($term);
        $term = preg_replace('/\s+/', ' ', $term);

        if ($term === '' || strtolower($term) === '(not set)') {
            return null;
        }

        return mb_substr($term, 0, 255);
    }

    private function normaliseGa4Date(string $date): ?string
    {
        $date = trim($date);

        if (preg_match('/^\d{8}$/', $date)) {
            return substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return null;
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
            throw new \RuntimeException(
                'GA4 credentials are missing. Configure OAuth credentials or service account JSON before syncing.'
            );
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

    private function getServiceAccountAccessToken(array $credentials): string
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

    private function base64UrlEncode(string|false $value): string
    {
        if ($value === false) {
            $value = '';
        }

        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
