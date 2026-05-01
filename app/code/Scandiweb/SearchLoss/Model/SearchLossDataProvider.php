<?php

namespace Scandiweb\SearchLoss\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Config\ScopeConfigInterface;

class SearchLossDataProvider
{
    private const XML_PATH_IDENTITY_ATTRIBUTES = 'searchloss/audit/identity_attributes';
    private const XML_PATH_IGNORED_TERMS = 'searchloss/audit/ignored_terms';
    private const XML_PATH_MINIMUM_POPULARITY = 'searchloss/audit/minimum_popularity';
    private const XML_PATH_LOW_ENGAGEMENT_MINIMUM_SEARCHES = 'searchloss/audit/low_engagement_minimum_searches';
    private const XML_PATH_LOW_PRODUCT_ENGAGEMENT_THRESHOLD = 'searchloss/audit/low_product_engagement_threshold';
    private const XML_PATH_LOW_ADD_TO_CART_THRESHOLD = 'searchloss/audit/low_add_to_cart_threshold';
    private const XML_PATH_LOW_PURCHASE_THRESHOLD = 'searchloss/audit/low_purchase_threshold';
    private const XML_PATH_HEALTHY_PURCHASE_THRESHOLD = 'searchloss/audit/healthy_purchase_threshold';

    private const DEFAULT_IDENTITY_ATTRIBUTES = 'manufacturer,brand,mpn,part_number,partnumber,product_code,model,oem,oem_number,supplier,vendor';
    private const DEFAULT_IGNORED_TERMS = 'test,testing,asdf,qwerty,lorem,ipsum,null,undefined';
    private const DEFAULT_LOW_ENGAGEMENT_MINIMUM_SEARCHES = 5;
    private const DEFAULT_LOW_PRODUCT_ENGAGEMENT_THRESHOLD = 20.0;
    private const DEFAULT_LOW_ADD_TO_CART_THRESHOLD = 10.0;
    private const DEFAULT_LOW_PURCHASE_THRESHOLD = 3.0;
    private const DEFAULT_HEALTHY_PURCHASE_THRESHOLD = 5.0;

    protected ResourceConnection $resource;
    protected ScopeConfigInterface $scopeConfig;

    public function __construct(
        ResourceConnection $resource,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->resource = $resource;
        $this->scopeConfig = $scopeConfig;
    }

    private function parseCsvConfig(?string $value): array
    {
        $items = array_map('trim', explode(',', (string)$value));

        $items = array_filter($items, function ($item) {
            return $item !== '';
        });

        return array_values(array_unique($items));
    }

    public function getConfiguredIdentityAttributes(): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_IDENTITY_ATTRIBUTES);

        return trim($value) !== '' ? $value : self::DEFAULT_IDENTITY_ATTRIBUTES;
    }

    public function getConfiguredIgnoredTerms(): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_IGNORED_TERMS);

        return trim($value) !== '' ? $value : self::DEFAULT_IGNORED_TERMS;
    }

    public function getConfiguredMinimumPopularity(): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_MINIMUM_POPULARITY);

        return max(1, $value);
    }

    public function getConfiguredLowEngagementMinimumSearches(): int
    {
        $value = (int)$this->scopeConfig->getValue(self::XML_PATH_LOW_ENGAGEMENT_MINIMUM_SEARCHES);

        return max(1, $value > 0 ? $value : self::DEFAULT_LOW_ENGAGEMENT_MINIMUM_SEARCHES);
    }

    public function getConfiguredLowProductEngagementThreshold(): float
    {
        return $this->getConfiguredPercentage(
            self::XML_PATH_LOW_PRODUCT_ENGAGEMENT_THRESHOLD,
            self::DEFAULT_LOW_PRODUCT_ENGAGEMENT_THRESHOLD
        );
    }

    public function getConfiguredLowAddToCartThreshold(): float
    {
        return $this->getConfiguredPercentage(
            self::XML_PATH_LOW_ADD_TO_CART_THRESHOLD,
            self::DEFAULT_LOW_ADD_TO_CART_THRESHOLD
        );
    }

    public function getConfiguredLowPurchaseThreshold(): float
    {
        return $this->getConfiguredPercentage(
            self::XML_PATH_LOW_PURCHASE_THRESHOLD,
            self::DEFAULT_LOW_PURCHASE_THRESHOLD
        );
    }

    public function getConfiguredHealthyPurchaseThreshold(): float
    {
        return $this->getConfiguredPercentage(
            self::XML_PATH_HEALTHY_PURCHASE_THRESHOLD,
            self::DEFAULT_HEALTHY_PURCHASE_THRESHOLD
        );
    }

    private function getConfiguredPercentage(string $path, float $default): float
    {
        $rawValue = $this->scopeConfig->getValue($path);

        if ($rawValue === null || trim((string)$rawValue) === '') {
            return $default;
        }

        $value = (float)$rawValue;

        if ($value < 0) {
            return 0.0;
        }

        if ($value > 100) {
            return 100.0;
        }

        return $value;
    }

    private function applyDateFilter($select, string $period)
    {
        if ($period === '7') {
            $select->where('updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        }

        if ($period === '30') {
            $select->where('updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
        }

        if ($period === '90') {
            $select->where('updated_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)');
        }

        if ($period === '365') {
            $select->where('updated_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)');
        }

        return $select;
    }

    private function getTotalSearches(string $period = 'all'): int
    {
        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from('search_query', ['total' => 'SUM(popularity)']);

        $this->applyDateFilter($select, $period);

        return (int)$connection->fetchOne($select);
    }

    private function getFailedSearchCount(string $period = 'all'): int
    {
        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from('search_query', ['total' => 'SUM(popularity)'])
            ->where('num_results = 0');

        $this->applyDateFilter($select, $period);

        return (int)$connection->fetchOne($select);
    }

    public function getAverageOrderValue(): float
    {
        $connection = $this->resource->getConnection();

        $aov = $connection->fetchOne(
            $connection->select()
                ->from('sales_order', ['avg_order_value' => 'AVG(grand_total)'])
                ->where('state != ?', 'canceled')
        );

        return (float)$aov;
    }

    public function getConversionRate(string $period = 'all'): float
    {
        $connection = $this->resource->getConnection();

        $searches = $this->getTotalSearches($period);

        $orders = (float)$connection->fetchOne(
            $connection->select()
                ->from('sales_order', ['total' => 'COUNT(*)'])
                ->where('state != ?', 'canceled')
        );

        if ($searches <= 0 || $orders <= 0) {
            return 0.02;
        }

        return $orders / $searches;
    }


    private function getProductAttributeId(string $attributeCode): int
    {
        $connection = $this->resource->getConnection();

        return (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', $attributeCode)
                ->where('entity_type_id = (
                    SELECT entity_type_id
                    FROM ' . $this->resource->getTableName('eav_entity_type') . '
                    WHERE entity_type_code = "catalog_product"
                    LIMIT 1
                )')
                ->limit(1)
        );
    }

    private function getSearchableAttributeSignals(): array
    {
        $connection = $this->resource->getConnection();

        $attributeTable = $this->resource->getTableName('eav_attribute');
        $catalogAttributeTable = $this->resource->getTableName('catalog_eav_attribute');
        $entityTypeTable = $this->resource->getTableName('eav_entity_type');

        $coreAttributeCodes = [
            'name',
            'sku',
            'description',
            'short_description',
        ];

        $identityAttributeCodes = $this->getIdentityAttributeCodes();
        $attributeCodesToCheck = array_values(array_unique(array_merge($coreAttributeCodes, $identityAttributeCodes)));

        $attributeRows = $connection->fetchAll(
            $connection->select()
                ->from(['ea' => $attributeTable], [
                    'attribute_code',
                    'frontend_label',
                ])
                ->join(
                    ['cea' => $catalogAttributeTable],
                    'cea.attribute_id = ea.attribute_id',
                    ['is_searchable', 'is_filterable']
                )
                ->join(
                    ['eet' => $entityTypeTable],
                    'eet.entity_type_id = ea.entity_type_id',
                    []
                )
                ->where('eet.entity_type_code = ?', 'catalog_product')
                ->where('ea.attribute_code IN (?)', $attributeCodesToCheck)
                ->order('ea.attribute_code ASC')
        );

        $foundCoreAttributes = [];
        $searchableCoreAttributes = [];
        $nonSearchableCoreAttributes = [];

        $foundIdentityAttributes = [];
        $searchableIdentityAttributes = [];
        $nonSearchableIdentityAttributes = [];

        foreach ($attributeRows as $attribute) {
            $code = (string)$attribute['attribute_code'];
            $isSearchable = (int)$attribute['is_searchable'] === 1;

            if (in_array($code, $coreAttributeCodes, true)) {
                $foundCoreAttributes[] = $code;

                if ($isSearchable) {
                    $searchableCoreAttributes[] = $code;
                } else {
                    $nonSearchableCoreAttributes[] = $code;
                }
            }

            if (in_array($code, $identityAttributeCodes, true)) {
                $foundIdentityAttributes[] = $code;

                if ($isSearchable) {
                    $searchableIdentityAttributes[] = $code;
                } else {
                    $nonSearchableIdentityAttributes[] = $code;
                }
            }
        }

        return [
            'coreAttributesFound' => empty($foundCoreAttributes) ? 'None' : implode(', ', $foundCoreAttributes),
            'searchableCoreAttributes' => empty($searchableCoreAttributes) ? 'None' : implode(', ', $searchableCoreAttributes),
            'nonSearchableCoreAttributes' => empty($nonSearchableCoreAttributes) ? 'None' : implode(', ', $nonSearchableCoreAttributes),

            'identityAttributesFoundList' => empty($foundIdentityAttributes) ? 'None' : implode(', ', $foundIdentityAttributes),
            'searchableIdentityAttributesList' => empty($searchableIdentityAttributes) ? 'None' : implode(', ', $searchableIdentityAttributes),
            'nonSearchableIdentityAttributesList' => empty($nonSearchableIdentityAttributes) ? 'None' : implode(', ', $nonSearchableIdentityAttributes),

            'coreAttributeSearchCoverage' => count($foundCoreAttributes) > 0
                ? count($searchableCoreAttributes) . ' of ' . count($foundCoreAttributes)
                : '0 of 0',
            'identityAttributeSearchCoverage' => count($foundIdentityAttributes) > 0
                ? count($searchableIdentityAttributes) . ' of ' . count($foundIdentityAttributes)
                : '0 of 0',
        ];
    }


    private function getIdentityAttributeCodes(): array
    {
        $configuredAttributes = $this->parseCsvConfig($this->getConfiguredIdentityAttributes());

        return empty($configuredAttributes)
            ? $this->parseCsvConfig(self::DEFAULT_IDENTITY_ATTRIBUTES)
            : $configuredAttributes;
    }

    private function getExistingIdentityAttributes(): array
    {
        $connection = $this->resource->getConnection();

        $attributeTable = $this->resource->getTableName('eav_attribute');
        $catalogAttributeTable = $this->resource->getTableName('catalog_eav_attribute');
        $entityTypeTable = $this->resource->getTableName('eav_entity_type');

        return $connection->fetchAll(
            $connection->select()
                ->from(['ea' => $attributeTable], [
                    'attribute_id',
                    'attribute_code',
                    'frontend_label',
                    'backend_type',
                ])
                ->join(
                    ['cea' => $catalogAttributeTable],
                    'cea.attribute_id = ea.attribute_id',
                    ['is_searchable', 'is_filterable']
                )
                ->join(
                    ['eet' => $entityTypeTable],
                    'eet.entity_type_id = ea.entity_type_id',
                    []
                )
                ->where('eet.entity_type_code = ?', 'catalog_product')
                ->where('ea.attribute_code IN (?)', $this->getIdentityAttributeCodes())
                ->order('ea.attribute_code ASC')
        );
    }

    private function getIdentityAttributeSignals(string $term, array $tokens): array
    {
        $connection = $this->resource->getConnection();

        $productVarcharTable = $this->resource->getTableName('catalog_product_entity_varchar');
        $productTextTable = $this->resource->getTableName('catalog_product_entity_text');
        $productIntTable = $this->resource->getTableName('catalog_product_entity_int');
        $optionValueTable = $this->resource->getTableName('eav_attribute_option_value');

        $identityAttributes = $this->getExistingIdentityAttributes();

        if (empty($identityAttributes)) {
            return [
                'identityAttributesFound' => 0,
                'searchableIdentityAttributes' => 0,
                'identityAttributeMatches' => 0,
                'matchedIdentityAttributes' => 'None',
            ];
        }

        $matchedAttributeCodes = [];
        $matchCount = 0;
        $searchableCount = 0;

        $valuesToCheck = array_values(array_unique(array_filter(array_merge([trim($term)], $tokens))));

        foreach ($identityAttributes as $attribute) {
            $attributeId = (int)$attribute['attribute_id'];
            $attributeCode = (string)$attribute['attribute_code'];
            $backendType = (string)$attribute['backend_type'];

            if ((int)$attribute['is_searchable'] === 1) {
                $searchableCount++;
            }

            $conditions = [];
            foreach ($valuesToCheck as $value) {
                $conditions[] = $connection->quoteInto('value LIKE ?', '%' . $value . '%');
            }

            if (empty($conditions)) {
                continue;
            }

            $attributeMatches = 0;

            if (in_array($backendType, ['varchar', 'text'], true)) {
                $valueTable = $backendType === 'text' ? $productTextTable : $productVarcharTable;

                $attributeMatches = (int)$connection->fetchOne(
                    $connection->select()
                        ->from($valueTable, ['total' => 'COUNT(DISTINCT entity_id)'])
                        ->where('attribute_id = ?', $attributeId)
                        ->where('(' . implode(' OR ', $conditions) . ')')
                );
            }

            if ($backendType === 'int') {
                $optionMatches = (int)$connection->fetchOne(
                    $connection->select()
                        ->from(['cpei' => $productIntTable], ['total' => 'COUNT(DISTINCT cpei.entity_id)'])
                        ->join(
                            ['eaov' => $optionValueTable],
                            'eaov.option_id = cpei.value',
                            []
                        )
                        ->where('cpei.attribute_id = ?', $attributeId)
                        ->where('(' . implode(' OR ', array_map(
                            fn($condition) => str_replace('value', 'eaov.value', $condition),
                            $conditions
                        )) . ')')
                );

                $attributeMatches = $optionMatches;
            }

            if ($attributeMatches > 0) {
                $matchCount += $attributeMatches;
                $matchedAttributeCodes[] = $attributeCode;
            }
        }

        $matchedAttributeCodes = array_values(array_unique($matchedAttributeCodes));

        return [
            'identityAttributesFound' => count($identityAttributes),
            'searchableIdentityAttributes' => $searchableCount,
            'identityAttributeMatches' => $matchCount,
            'matchedIdentityAttributes' => empty($matchedAttributeCodes) ? 'None' : implode(', ', $matchedAttributeCodes),
        ];
    }

    private function getProductVisibilitySignals(string $term, array $tokens): array
    {
        $connection = $this->resource->getConnection();

        $productEntityTable = $this->resource->getTableName('catalog_product_entity');
        $productVarcharTable = $this->resource->getTableName('catalog_product_entity_varchar');
        $productIntTable = $this->resource->getTableName('catalog_product_entity_int');
        $productWebsiteTable = $this->resource->getTableName('catalog_product_website');
        $productCategoryTable = $this->resource->getTableName('catalog_category_product');
        $stockStatusTable = $this->resource->getTableName('cataloginventory_stock_status');

        $nameAttributeId = $this->getProductAttributeId('name');
        $statusAttributeId = $this->getProductAttributeId('status');
        $visibilityAttributeId = $this->getProductAttributeId('visibility');

        if ($nameAttributeId <= 0) {
            return [
                'relatedProductsChecked' => 0,
                'enabledProducts' => 0,
                'disabledProducts' => 0,
                'visibleInSearch' => 0,
                'notVisibleInSearch' => 0,
                'assignedToWebsite' => 0,
                'notAssignedToWebsite' => 0,
                'assignedToCategory' => 0,
                'notAssignedToCategory' => 0,
                'inStockProducts' => 0,
                'outOfStockProducts' => 0,
            ];
        }

        $cleanTerm = trim($term);
        $likeTerm = '%' . $cleanTerm . '%';

        $conditions = [
            $connection->quoteInto('p.sku LIKE ?', $likeTerm),
            $connection->quoteInto('(name.value LIKE ?)', $likeTerm),
        ];

        foreach ($tokens as $token) {
            $conditions[] = $connection->quoteInto('(name.value LIKE ?)', '%' . $token . '%');
            $conditions[] = $connection->quoteInto('p.sku LIKE ?', '%' . $token . '%');
        }

        $baseSelect = $connection->select()
            ->from(['p' => $productEntityTable], ['entity_id'])
            ->joinLeft(
                ['name' => $productVarcharTable],
                'name.entity_id = p.entity_id AND name.attribute_id = ' . (int)$nameAttributeId,
                []
            )
            ->where('(' . implode(' OR ', $conditions) . ')')
            ->group('p.entity_id')
            ->limit(50);

        $productIds = $connection->fetchCol($baseSelect);

        if (empty($productIds)) {
            return [
                'relatedProductsChecked' => 0,
                'enabledProducts' => 0,
                'disabledProducts' => 0,
                'visibleInSearch' => 0,
                'notVisibleInSearch' => 0,
                'assignedToWebsite' => 0,
                'notAssignedToWebsite' => 0,
                'assignedToCategory' => 0,
                'notAssignedToCategory' => 0,
                'inStockProducts' => 0,
                'outOfStockProducts' => 0,
            ];
        }

        $enabledProducts = 0;
        $disabledProducts = 0;
        $visibleInSearch = 0;
        $notVisibleInSearch = 0;
        $inStockProducts = 0;
        $outOfStockProducts = 0;

        if ($statusAttributeId > 0) {
            $enabledProducts = (int)$connection->fetchOne(
                $connection->select()
                    ->from($productIntTable, ['total' => 'COUNT(DISTINCT entity_id)'])
                    ->where('attribute_id = ?', $statusAttributeId)
                    ->where('entity_id IN (?)', $productIds)
                    ->where('value = ?', 1)
            );

            $disabledProducts = (int)$connection->fetchOne(
                $connection->select()
                    ->from($productIntTable, ['total' => 'COUNT(DISTINCT entity_id)'])
                    ->where('attribute_id = ?', $statusAttributeId)
                    ->where('entity_id IN (?)', $productIds)
                    ->where('value = ?', 2)
            );
        }

        if ($visibilityAttributeId > 0) {
            $visibleInSearch = (int)$connection->fetchOne(
                $connection->select()
                    ->from($productIntTable, ['total' => 'COUNT(DISTINCT entity_id)'])
                    ->where('attribute_id = ?', $visibilityAttributeId)
                    ->where('entity_id IN (?)', $productIds)
                    ->where('value IN (?)', [3, 4])
            );

            $notVisibleInSearch = (int)$connection->fetchOne(
                $connection->select()
                    ->from($productIntTable, ['total' => 'COUNT(DISTINCT entity_id)'])
                    ->where('attribute_id = ?', $visibilityAttributeId)
                    ->where('entity_id IN (?)', $productIds)
                    ->where('value IN (?)', [1, 2])
            );
        }


        $assignedToWebsite = (int)$connection->fetchOne(
            $connection->select()
                ->from($productWebsiteTable, ['total' => 'COUNT(DISTINCT product_id)'])
                ->where('product_id IN (?)', $productIds)
        );

        $assignedToCategory = (int)$connection->fetchOne(
            $connection->select()
                ->from($productCategoryTable, ['total' => 'COUNT(DISTINCT product_id)'])
                ->where('product_id IN (?)', $productIds)
        );

        if ($connection->isTableExists($stockStatusTable)) {
            $inStockProducts = (int)$connection->fetchOne(
                $connection->select()
                    ->from($stockStatusTable, ['total' => 'COUNT(DISTINCT product_id)'])
                    ->where('product_id IN (?)', $productIds)
                    ->where('stock_status = ?', 1)
            );

            $outOfStockProducts = (int)$connection->fetchOne(
                $connection->select()
                    ->from($stockStatusTable, ['total' => 'COUNT(DISTINCT product_id)'])
                    ->where('product_id IN (?)', $productIds)
                    ->where('stock_status = ?', 0)
            );
        }

        $notAssignedToWebsite = max(0, count($productIds) - $assignedToWebsite);
        $notAssignedToCategory = max(0, count($productIds) - $assignedToCategory);

        return [
            'relatedProductsChecked' => count($productIds),
            'enabledProducts' => $enabledProducts,
            'disabledProducts' => $disabledProducts,
            'visibleInSearch' => $visibleInSearch,
            'notVisibleInSearch' => $notVisibleInSearch,
            'assignedToWebsite' => $assignedToWebsite,
            'notAssignedToWebsite' => $notAssignedToWebsite,
            'assignedToCategory' => $assignedToCategory,
            'notAssignedToCategory' => $notAssignedToCategory,
            'inStockProducts' => $inStockProducts,
            'outOfStockProducts' => $outOfStockProducts,
        ];
    }

    private function getCatalogueSignals(string $term): array
    {
        $connection = $this->resource->getConnection();
        $cleanTerm = trim($term);
        $likeTerm = '%' . $cleanTerm . '%';

        $productEntityTable = $this->resource->getTableName('catalog_product_entity');
        $productVarcharTable = $this->resource->getTableName('catalog_product_entity_varchar');
        $categoryVarcharTable = $this->resource->getTableName('catalog_category_entity_varchar');
        $eavAttributeTable = $this->resource->getTableName('eav_attribute');

        $skuMatches = (int)$connection->fetchOne(
            $connection->select()
                ->from($productEntityTable, ['total' => 'COUNT(*)'])
                ->where('sku LIKE ?', $likeTerm)
        );

        $productNameAttributeId = (int)$connection->fetchOne(
            $connection->select()
                ->from($eavAttributeTable, ['attribute_id'])
                ->where('attribute_code = ?', 'name')
                ->where('entity_type_id = (
                    SELECT entity_type_id
                    FROM ' . $this->resource->getTableName('eav_entity_type') . '
                    WHERE entity_type_code = "catalog_product"
                    LIMIT 1
                )')
                ->limit(1)
        );

        $categoryNameAttributeId = (int)$connection->fetchOne(
            $connection->select()
                ->from($eavAttributeTable, ['attribute_id'])
                ->where('attribute_code = ?', 'name')
                ->where('entity_type_id = (
                    SELECT entity_type_id
                    FROM ' . $this->resource->getTableName('eav_entity_type') . '
                    WHERE entity_type_code = "catalog_category"
                    LIMIT 1
                )')
                ->limit(1)
        );

        $productNameMatches = 0;

        if ($productNameAttributeId > 0) {
            $productNameMatches = (int)$connection->fetchOne(
                $connection->select()
                    ->from($productVarcharTable, ['total' => 'COUNT(DISTINCT entity_id)'])
                    ->where('attribute_id = ?', $productNameAttributeId)
                    ->where('value LIKE ?', $likeTerm)
            );
        }

        $categoryNameMatches = 0;

        if ($categoryNameAttributeId > 0) {
            $categoryNameMatches = (int)$connection->fetchOne(
                $connection->select()
                    ->from($categoryVarcharTable, ['total' => 'COUNT(DISTINCT entity_id)'])
                    ->where('attribute_id = ?', $categoryNameAttributeId)
                    ->where('value LIKE ?', $likeTerm)
            );
        }

        return [
            'skuMatches' => $skuMatches,
            'productNameMatches' => $productNameMatches,
            'categoryNameMatches' => $categoryNameMatches,
            'hasSkuMatch' => $skuMatches > 0,
            'hasProductNameMatch' => $productNameMatches > 0,
            'hasCategoryNameMatch' => $categoryNameMatches > 0,
            'hasCatalogueMatch' => ($skuMatches + $productNameMatches + $categoryNameMatches) > 0,
        ];
    }

    private function getSearchTokens(string $term): array
    {
        $normalized = strtolower((string)preg_replace('/[^a-z0-9]+/i', ' ', $term));
        $rawTokens = preg_split('/\s+/', trim($normalized), -1, PREG_SPLIT_NO_EMPTY);

        $stopWords = [
            'and' => true,
            'the' => true,
            'for' => true,
            'with' => true,
            'from' => true,
            'this' => true,
            'that' => true,
        ];

        $tokens = [];

        foreach ($rawTokens as $token) {
            if (strlen($token) < 3 || isset($stopWords[$token])) {
                continue;
            }

            $tokens[$token] = true;
        }

        return array_slice(array_keys($tokens), 0, 8);
    }

    private function getCatalogEvidence(string $term): array
    {
        $signals = $this->getCatalogueSignals($term);
        $tokens = $this->getSearchTokens($term);
        $visibilitySignals = $this->getProductVisibilitySignals($term, $tokens);
        $identitySignals = $this->getIdentityAttributeSignals($term, $tokens);
        $searchableAttributeSignals = $this->getSearchableAttributeSignals();

        $connection = $this->resource->getConnection();
        $productVarcharTable = $this->resource->getTableName('catalog_product_entity_varchar');
        $categoryVarcharTable = $this->resource->getTableName('catalog_category_entity_varchar');
        $eavAttributeTable = $this->resource->getTableName('eav_attribute');
        $eavEntityTypeTable = $this->resource->getTableName('eav_entity_type');

        $productNameAttributeId = (int)$connection->fetchOne(
            $connection->select()
                ->from($eavAttributeTable, ['attribute_id'])
                ->where('attribute_code = ?', 'name')
                ->where('entity_type_id = (
                    SELECT entity_type_id
                    FROM ' . $eavEntityTypeTable . '
                    WHERE entity_type_code = "catalog_product"
                    LIMIT 1
                )')
                ->limit(1)
        );

        $categoryNameAttributeId = (int)$connection->fetchOne(
            $connection->select()
                ->from($eavAttributeTable, ['attribute_id'])
                ->where('attribute_code = ?', 'name')
                ->where('entity_type_id = (
                    SELECT entity_type_id
                    FROM ' . $eavEntityTypeTable . '
                    WHERE entity_type_code = "catalog_category"
                    LIMIT 1
                )')
                ->limit(1)
        );

        $relatedProductMatches = 0;
        $relatedCategoryMatches = 0;

        if (!empty($tokens) && $productNameAttributeId > 0) {
            $conditions = [];

            foreach ($tokens as $token) {
                $conditions[] = $connection->quoteInto('value LIKE ?', '%' . $token . '%');
            }

            $relatedProductMatches = (int)$connection->fetchOne(
                $connection->select()
                    ->from($productVarcharTable, ['total' => 'COUNT(DISTINCT entity_id)'])
                    ->where('attribute_id = ?', $productNameAttributeId)
                    ->where('(' . implode(' OR ', $conditions) . ')')
            );
        }

        if (!empty($tokens) && $categoryNameAttributeId > 0) {
            $conditions = [];

            foreach ($tokens as $token) {
                $conditions[] = $connection->quoteInto('value LIKE ?', '%' . $token . '%');
            }

            $relatedCategoryMatches = (int)$connection->fetchOne(
                $connection->select()
                    ->from($categoryVarcharTable, ['total' => 'COUNT(DISTINCT entity_id)'])
                    ->where('attribute_id = ?', $categoryNameAttributeId)
                    ->where('(' . implode(' OR ', $conditions) . ')')
            );
        }

        if ((int)$signals['skuMatches'] > 0) {
            $status = 'SKU signal found';
            $suggestion = 'A SKU-like match exists, but search still failed. Review SKU search behavior, indexing, and whether identifier fields are searchable.';
        } elseif ((int)$signals['productNameMatches'] > 0 || (int)$signals['categoryNameMatches'] > 0) {
            $status = 'Exact catalog wording found';
            $suggestion = 'Magento has matching catalog wording, but search still failed. Review indexing, searchable attributes, synonyms, and whether the matched fields are included in search.';
        } elseif ($relatedProductMatches > 0 || $relatedCategoryMatches > 0) {
            $status = 'Related catalog wording found';
            $suggestion = 'Magento has related catalog wording, but the customer search still failed. This usually means product naming, searchable attribute coverage, synonyms, or search ranking need review.';
        } else {
            $status = 'No obvious catalog signal found';
            $suggestion = 'No clear catalog signal was found. This may be true missing demand, or the product may exist under wording customers do not use.';
        }

        return [
            ['label' => 'Search words checked', 'value' => empty($tokens) ? 'None' : implode(', ', $tokens)],
            ['label' => 'SKU matches', 'value' => (string)$signals['skuMatches']],
            ['label' => 'Full-phrase product matches', 'value' => (string)$signals['productNameMatches']],
            ['label' => 'Keyword product matches', 'value' => (string)$relatedProductMatches],
            ['label' => 'Related product matches', 'value' => (string)$visibilitySignals['relatedProductsChecked']],
            ['label' => 'Enabled product matches', 'value' => (string)$visibilitySignals['enabledProducts']],
            ['label' => 'Disabled product matches', 'value' => (string)$visibilitySignals['disabledProducts']],
            ['label' => 'Visible in search', 'value' => (string)$visibilitySignals['visibleInSearch']],
            ['label' => 'Not visible in search', 'value' => (string)$visibilitySignals['notVisibleInSearch']],
            ['label' => 'Assigned to website', 'value' => (string)$visibilitySignals['assignedToWebsite']],
            ['label' => 'Not assigned to website', 'value' => (string)$visibilitySignals['notAssignedToWebsite']],
            ['label' => 'Assigned to category', 'value' => (string)$visibilitySignals['assignedToCategory']],
            ['label' => 'Not assigned to category', 'value' => (string)$visibilitySignals['notAssignedToCategory']],
            ['label' => 'In stock product matches', 'value' => (string)$visibilitySignals['inStockProducts']],
            ['label' => 'Out of stock product matches', 'value' => (string)$visibilitySignals['outOfStockProducts']],
            ['label' => 'Core product fields found', 'value' => $searchableAttributeSignals['coreAttributesFound']],
            ['label' => 'Searchable core product fields', 'value' => $searchableAttributeSignals['searchableCoreAttributes']],
            ['label' => 'Non-searchable core product fields', 'value' => $searchableAttributeSignals['nonSearchableCoreAttributes']],
            ['label' => 'Core field search coverage', 'value' => $searchableAttributeSignals['coreAttributeSearchCoverage']],
            ['label' => 'Identity fields found', 'value' => $searchableAttributeSignals['identityAttributesFoundList']],
            ['label' => 'Searchable identity fields', 'value' => $searchableAttributeSignals['searchableIdentityAttributesList']],
            ['label' => 'Non-searchable identity fields', 'value' => $searchableAttributeSignals['nonSearchableIdentityAttributesList']],
            ['label' => 'Identity field search coverage', 'value' => $searchableAttributeSignals['identityAttributeSearchCoverage']],
            ['label' => 'Identity attributes found', 'value' => (string)$identitySignals['identityAttributesFound']],
            ['label' => 'Searchable identity attributes', 'value' => (string)$identitySignals['searchableIdentityAttributes']],
            ['label' => 'Identity attribute matches', 'value' => (string)$identitySignals['identityAttributeMatches']],
            ['label' => 'Matched identity attributes', 'value' => $identitySignals['matchedIdentityAttributes']],
            ['label' => 'Full-phrase category matches', 'value' => (string)$signals['categoryNameMatches']],
            ['label' => 'Keyword category matches', 'value' => (string)$relatedCategoryMatches],
            ['label' => 'Catalog signal', 'value' => $status],
            ['label' => 'What this suggests', 'value' => $suggestion],
        ];
    }

    private function getFixType(string $term): string
    {
        $normalized = strtolower(trim($term));
        $tokens = $this->getSearchTokens($term);
        $catalogueSignals = $this->getCatalogueSignals($term);
        $visibilitySignals = $this->getProductVisibilitySignals($term, $tokens);
        $identitySignals = $this->getIdentityAttributeSignals($term, $tokens);

        if ((int)$visibilitySignals['relatedProductsChecked'] > 0) {
            if ((int)$visibilitySignals['disabledProducts'] > 0 && (int)$visibilitySignals['enabledProducts'] <= 0) {
                return 'Product exists but is disabled';
            }

            if ((int)$visibilitySignals['notAssignedToWebsite'] > 0 && (int)$visibilitySignals['assignedToWebsite'] <= 0) {
                return 'Product exists but is not assigned to website';
            }

            if ((int)$visibilitySignals['notVisibleInSearch'] > 0 && (int)$visibilitySignals['visibleInSearch'] <= 0) {
                return 'Product exists but is not visible in search';
            }

            if ((int)$visibilitySignals['notAssignedToCategory'] > 0 && (int)$visibilitySignals['assignedToCategory'] <= 0) {
                return 'Product exists but is not assigned to category';
            }

            if ((int)$visibilitySignals['outOfStockProducts'] > 0 && (int)$visibilitySignals['inStockProducts'] <= 0) {
                return 'Product exists but may be out of stock';
            }

            if ((int)$visibilitySignals['visibleInSearch'] > 0) {
                return 'Product exists but search is not matching it';
            }

            return 'Product exists but is not showing';
        }

        if ((int)$identitySignals['identityAttributeMatches'] > 0) {
            return 'Brand or product terms are missing';
        }

        if ($catalogueSignals['hasSkuMatch']) {
            return 'SKU or part number is not matching';
        }

        if ($catalogueSignals['hasProductNameMatch'] || $catalogueSignals['hasCategoryNameMatch']) {
            return 'Product exists but search is not matching it';
        }

        $hasIdentifierLanguage = preg_match('/\b(sku|part|part\s*number|mpn|oem|cross\s*reference|xref|barcode|serial|model)\b/i', $normalized);
        $hasCompactIdentifier = preg_match('/\b[a-z]{2,}[-\.]?\d{2,}[a-z0-9-\.]*\b/i', $term)
            || preg_match('/\b\d{2,}[-\.]?[a-z]{1,}[a-z0-9-\.]*\b/i', $term);
        $hasDimensionLikeIdentifier = preg_match('/\b\d+(?:x\d+){1,}\b/i', $term);
        $hasHyphenatedNumericIdentifier = preg_match('/\b\d{2,}[-\.]\d{2,}\b/i', $term);

        if ($hasIdentifierLanguage || $hasCompactIdentifier || $hasDimensionLikeIdentifier || $hasHyphenatedNumericIdentifier) {
            return 'SKU or part number is not matching';
        }

        if (preg_match('/[^a-z0-9\s\-\.]/i', $term) || preg_match('/\b[a-z]+\.[a-z]+\b/i', $normalized)) {
            return 'Spelling or format variant';
        }

        if (preg_match('/\b(19|20)\d{2}\b/', $normalized) || str_word_count($normalized) >= 3) {
            return 'Fitment or use case is unclear';
        }

        if (str_word_count($normalized) >= 2) {
            return 'Product or category may be missing';
        }

        if (str_word_count($normalized) <= 1) {
            return 'Search term is too broad or unclear';
        }

        return 'Needs manual review';
    }

    private function getSuggestedFix(string $term, string $fixType): string
    {

        $cleanTerm = trim($term);

        switch ($fixType) {
            case 'Product exists but search is not matching it':
                return sprintf(
                    'Magento appears to have related products for "%s", and they may already be enabled and visible. Review search indexing, searchable attributes, synonyms, and customer wording because the issue looks like matching rather than missing catalogue coverage.',
                    $cleanTerm
                );

            case 'Product exists but is disabled':
                return sprintf(
                    'Related products were found for "%s", but they appear to be disabled. Review whether these products should be enabled, replaced, redirected, or excluded from search demand reporting.',
                    $cleanTerm
                );

            case 'Product exists but is not visible in search':
                return sprintf(
                    'Related products were found for "%s", but they do not appear to be visible in search. Review product visibility settings, searchable attributes, indexing, and storefront search behaviour.',
                    $cleanTerm
                );

            case 'Product exists but is not assigned to website':
                return sprintf(
                    'Related products were found for "%s", but they do not appear to be assigned to the current Magento website. Review website assignment, store view availability, and indexing.',
                    $cleanTerm
                );

            case 'Product exists but is not assigned to category':
                return sprintf(
                    'Related products were found for "%s", but they do not appear to be assigned to a category. Review category assignment, product routing, search visibility, and whether customers need a better landing path.',
                    $cleanTerm
                );

            case 'Product exists but may be out of stock':
                return sprintf(
                    'Related products were found for "%s", but they appear to be out of stock. Review stock status, salable quantity, backorder rules, and whether customers should be routed to an available alternative.',
                    $cleanTerm
                );

            case 'Brand or product terms are missing':
                return sprintf(
                    'Check whether matching products have the right brand, manufacturer, product family, model, and product-type data for "%s". If products exist, add the missing terms to searchable attributes and improve product naming or copy where useful.',
                    $cleanTerm
                );

            case 'Customers use different wording':
                return sprintf(
                    'Check whether "%s" means the same thing as an existing product or category. If it does, add it as a synonym or searchable term, and update product/category copy only where the wording is accurate and natural.',
                    $cleanTerm
                );

            case 'SKU or part number is not matching':
                return sprintf(
                    'Check whether "%s" matches a SKU, manufacturer part number, alternate part number, old part number, replacement part, barcode, or common customer-used format. Prioritise exact and normalised matches before broad keyword results.',
                    $cleanTerm
                );

            case 'Product or category may be missing':
                return sprintf(
                    'Check whether the store sells "%s", an equivalent product, or a close substitute. If it exists, improve findability. If not, treat repeated searches as catalog demand or route customers to the closest helpful alternative.',
                    $cleanTerm
                );

            case 'Spelling or format variant':
                return sprintf(
                    'Check whether "%s" is a common typo, abbreviation, spacing, punctuation, or singular/plural variant. Add it only when the intended product is clear, and avoid broad matches for SKU-like terms.',
                    $cleanTerm
                );

            case 'Fitment or use case is unclear':
                return sprintf(
                    'Check whether "%s" describes a specific application, compatibility need, model, size, material, system, or use case. If relevant products exist, add structured fitment data and clear product copy that connects the need to the right products.',
                    $cleanTerm
                );

            case 'Search term is too broad or unclear':
                return sprintf(
                    'Do not force a narrow synonym or redirect for "%s". Help customers narrow the search with better categories, filters, suggested terms, and result ordering.',
                    $cleanTerm
                );

            case 'Results are weak or badly ranked':
                return sprintf(
                    'Search "%s" manually and review the top results. If the right products exist but rank poorly, adjust searchable attributes, search weights, product data, ranking rules, or merchandising boosts.',
                    $cleanTerm
                );

            default:
                return sprintf(
                    'Check whether "%s" maps to a product, category, SKU, brand, synonym, redirect, compatibility need, or catalog gap. If it repeats or has high revenue at risk, assign it to a clearer fix type after review.',
                    $cleanTerm
                );
        }
    }

    private function getShortSuggestedAction(string $term, string $fixType): string
    {
        switch ($fixType) {
            case 'Product exists but search is not matching it':
                return 'Review indexing, searchable attributes, synonyms, and search matching.';

            case 'Product exists but is disabled':
                return 'Review whether matching disabled products should be enabled or redirected.';

            case 'Product exists but is not visible in search':
                return 'Review product visibility settings and search indexing.';

            case 'Product exists but is not assigned to website':
                return 'Assign matching products to the correct Magento website if appropriate.';

            case 'Product exists but is not assigned to category':
                return 'Assign matching products to useful categories or landing paths.';

            case 'Product exists but is not showing':
                return 'Review search indexing, searchable attributes, and result visibility.';

            case 'Brand or product terms are missing':
                return 'Add missing brand or product terms to searchable product data.';

            case 'Customers use different wording':
                return 'Map customer wording to the catalog language with safe synonyms.';

            case 'SKU or part number is not matching':
                return 'Review SKU, part-number, and alternate identifier search matching.';

            case 'Product or category may be missing':
                return 'Review catalog coverage or route customers to the closest category.';

            case 'Spelling or format variant':
                return 'Add safe spelling, spacing, punctuation, or format variants.';

            case 'Fitment or use case is unclear':
                return 'Improve fitment, compatibility, size, or use-case product data.';

            case 'Search term is too broad or unclear':
                return 'Improve categories, filters, suggestions, and result ordering.';

            case 'Results are weak or badly ranked':
                return 'Review result ranking, searchable attributes, and merchandising rules.';

            default:
                return 'Review matching products, categories, synonyms, redirects, and product data.';
        }
    }

    private function getPlainEnglishMeaning(string $term, string $fixType): string
    {

        $cleanTerm = trim($term);
        $visibilitySignals = $this->getProductVisibilitySignals($term, $this->getSearchTokens($term));

        if ((int)$visibilitySignals['relatedProductsChecked'] > 0) {
            if ((int)$visibilitySignals['disabledProducts'] > 0 && (int)$visibilitySignals['enabledProducts'] <= 0) {
                return sprintf(
                    'Related products were found for "%s", but they appear to be disabled in Magento. Customers may be searching for products that exist in the catalogue but are not currently sellable or visible.',
                    $cleanTerm
                );
            }

            if ((int)$visibilitySignals['notAssignedToWebsite'] > 0 && (int)$visibilitySignals['assignedToWebsite'] <= 0) {
                return sprintf(
                    'Related products were found for "%s", but they do not appear to be assigned to a Magento website. The products may exist in the catalogue, but not be available on the storefront customers are searching.',
                    $cleanTerm
                );
            }

            if ((int)$visibilitySignals['notAssignedToCategory'] > 0 && (int)$visibilitySignals['assignedToCategory'] <= 0) {
                return sprintf(
                    'Related products were found for "%s", but they do not appear to be assigned to a category. Customers may not be able to browse to them, and search may have less useful catalogue context.',
                    $cleanTerm
                );
            }

            if ((int)$visibilitySignals['notVisibleInSearch'] > 0 && (int)$visibilitySignals['visibleInSearch'] <= 0) {
                return sprintf(
                    'Related products were found for "%s", but they do not appear to be visible in search. Magento may have the product data, but customers may not be able to reach it through site search.',
                    $cleanTerm
                );
            }

            if ((int)$visibilitySignals['outOfStockProducts'] > 0 && (int)$visibilitySignals['inStockProducts'] <= 0) {
                return sprintf(
                    'Related products were found for "%s", but they appear to be out of stock. Customers may be searching for products the catalogue knows about, but cannot currently buy.',
                    $cleanTerm
                );
            }

            if ((int)$visibilitySignals['notAssignedToWebsite'] > 0 || (int)$visibilitySignals['notAssignedToCategory'] > 0) {
                return sprintf(
                    'Related products were found for "%s", but some may not be assigned to a website or category. Review website assignment, category assignment, visibility, and indexing before treating this as missing catalogue demand.',
                    $cleanTerm
                );
            }

            if ((int)$visibilitySignals['visibleInSearch'] > 0) {
                return sprintf(
                    'Related products for "%s" appear to exist and be visible in search, but Magento still returned zero results. This points toward indexing, searchable attributes, synonyms, or search configuration rather than a simple catalogue gap.',
                    $cleanTerm
                );
            }

            return sprintf(
                'Related products were found for "%s", but Magento still returned zero results. Review product visibility, website assignment, category assignment, searchable attributes, synonyms, indexing, and customer wording before treating this as missing catalogue demand.',
                $cleanTerm
            );
        }

        switch ($fixType) {
            case 'Product exists but is not showing':
                return sprintf(
                    'A matching product or category may already exist for "%s", but Magento search may not be showing it to customers.',
                    $cleanTerm
                );

            case 'Brand or product terms are missing':
                return sprintf(
                    '"%s" looks like a brand, manufacturer, model, or product-type search. Relevant products may exist, but they may not include the right searchable brand or product terms.',
                    $cleanTerm
                );

            case 'Customers use different wording':
                return sprintf(
                    'Customers may be using "%s" to describe something the catalog calls by a different name.',
                    $cleanTerm
                );

            case 'SKU or part number is not matching':
                return sprintf(
                    '"%s" looks like a SKU, part number, manufacturer number, old part number, or customer-used identifier that search is not matching correctly.',
                    $cleanTerm
                );

            case 'Product or category may be missing':
                return sprintf(
                    'Customers searched for "%s", but Magento returned no useful result. This may point to a missing product, weak product data, or a search term that needs routing to the right product or category.',
                    $cleanTerm
                );

            case 'Spelling or format variant':
                return sprintf(
                    '"%s" may be a typo, abbreviation, spacing variant, punctuation variant, or singular/plural version of a product customers expect to find.',
                    $cleanTerm
                );

            case 'Fitment or use case is unclear':
                return sprintf(
                    '"%s" may describe a specific fitment, application, size, model, material, system, or use case that product data does not clearly answer.',
                    $cleanTerm
                );

            case 'Search term is too broad or unclear':
                return sprintf(
                    '"%s" is broad or ambiguous, so customers may need better categories, filters, suggested terms, or result ordering to narrow their intent.',
                    $cleanTerm
                );

            case 'Results are weak or badly ranked':
                return sprintf(
                    'The right products for "%s" may exist, but they may be buried below weaker or irrelevant search results.',
                    $cleanTerm
                );

            default:
                return sprintf(
                    'Customers are telling you they want "%s", but Magento search may not be connecting that demand to a useful product, category, synonym, or search result.',
                    $cleanTerm
                );
        }
    }


    private function getFixEffortBucket(string $fixType): string
    {
        switch ($fixType) {
            case 'Product exists but is disabled':
            case 'Product exists but is not visible in search':
            case 'Product exists but is not assigned to website':
            case 'Product exists but is not assigned to category':
            case 'Product exists but may be out of stock':
                return 'Quick admin/config fix';

            case 'Product exists but search is not matching it':
            case 'Product exists but is not showing':
                return 'Attribute/search configuration fix';

            case 'SKU or part number is not matching':
            case 'Brand or product terms are missing':
            case 'Fitment or use case is unclear':
                return 'Catalogue data fix';

            case 'Customers use different wording':
            case 'Spelling or format variant':
                return 'Synonym/search term fix';

            case 'Search term is too broad or unclear':
            case 'Results are weak or badly ranked':
                return 'Search relevance/ranking review';

            case 'Product or category may be missing':
                return 'Catalogue coverage review';

            default:
                return 'Manual review';
        }
    }

    private function getMagentoFixSteps(string $term, string $fixType): array
    {
        switch ($fixType) {
            case 'Product exists but search is not matching it':
                return [
                    'Search the term manually in Magento admin and on the storefront.',
                    'Confirm which related products should appear for this search.',
                    'Review product names, searchable attributes, synonyms, and search weights.',
                    'Check whether Magento search indexing is current.',
                    'Reindex Magento search and test the customer search again.',
                ];

            case 'Product exists but is disabled':
                return [
                    'Review the matching disabled products in Magento.',
                    'Confirm whether they should be sellable, replaced, redirected, or hidden.',
                    'If they should be sellable, enable them and review stock, website, category, and visibility settings.',
                    'If they should not be sellable, route customers to the closest active alternative where appropriate.',
                    'Reindex Magento search and test the customer search again.',
                ];

            case 'Product exists but is not visible in search':
                return [
                    'Open the matching products in Magento admin.',
                    'Review product visibility and confirm they are visible in search or catalog/search where appropriate.',
                    'Check searchable attributes, product status, stock, and website assignment.',
                    'Reindex Magento search data.',
                    'Test the customer search again on the storefront.',
                ];

            case 'Product exists but is not assigned to website':
                return [
                    'Open the matching products in Magento admin.',
                    'Check whether they are assigned to the correct website and store view.',
                    'Confirm status, visibility, stock, and category assignment.',
                    'Assign products to the correct website if they should be available there.',
                    'Reindex Magento search and test the customer search again.',
                ];

            case 'Product exists but is not assigned to category':
                return [
                    'Open the matching products in Magento admin.',
                    'Check whether they are assigned to a useful customer-facing category.',
                    'If needed, assign them to the best category or create a clearer landing path.',
                    'Review product naming and searchable attributes.',
                    'Reindex Magento search and test the customer search again.',
                ];

            case 'Product exists but may be out of stock':
                return [
                    'Open the matching products in Magento admin.',
                    'Review stock status, salable quantity, source inventory, and backorder settings.',
                    'If products should be available, correct stock/salable quantity and reindex inventory/search data.',
                    'If products are genuinely unavailable, route customers to the closest in-stock alternative where appropriate.',
                    'Test the customer search again on the storefront.',
                ];

            case 'Product exists but is not showing':
                return [
                    'Search the term manually in Magento admin and on the storefront.',
                    'Review matching product status, visibility, website assignment, category assignment, and stock.',
                    'Check searchable attributes, synonyms, redirects, and indexing.',
                    'Confirm the product should appear for this customer search.',
                    'Reindex Magento search and test again.',
                ];

            case 'Brand or product terms are missing':
                return [
                    'Search Magento products for the term and close variations.',
                    'Review brand, manufacturer, model, product family, and product-type attributes.',
                    'Add missing terms to searchable product attributes where accurate.',
                    'Improve product names or descriptions where useful.',
                    'Reindex Magento search and test the customer search again.',
                ];

            case 'Customers use different wording':
                return [
                    'Check whether the term maps to an existing product or category.',
                    'Identify the catalog wording currently used for the same product.',
                    'Add safe synonyms or searchable terms where the meaning is the same.',
                    'Update product or category copy only where the wording is accurate.',
                    'Reindex Magento search and test the customer search again.',
                ];

            case 'SKU or part number is not matching':
                return [
                    'Check whether the term matches a SKU, manufacturer part number, alternate part number, old part number, replacement part, or barcode.',
                    'Review whether those identifiers are stored on the correct product.',
                    'Make sure SKU and part-number fields are searchable where appropriate.',
                    'Add alternate identifiers to product data if they are valid.',
                    'Reindex Magento search data and test exact-match search behavior.',
                ];

            case 'Product or category may be missing':
                return [
                    'Confirm whether the store sells this product, an equivalent product, or a close substitute.',
                    'If products exist, improve product naming, searchable attributes, category assignment, and search visibility.',
                    'If a relevant category exists, route customers to the best category or landing page.',
                    'If the store does not sell it, treat repeated searches as catalog demand.',
                    'Reindex Magento search and test the customer search again.',
                ];

            case 'Spelling or format variant':
                return [
                    'Check whether the term is a typo, abbreviation, spacing issue, punctuation variant, or singular/plural variant.',
                    'Confirm the intended product or category before adding a synonym.',
                    'Add safe variants only where the customer intent is clear.',
                    'Avoid broad matches for SKU-like or part-number-like terms.',
                    'Reindex Magento search and test the customer search again.',
                ];

            case 'Fitment or use case is unclear':
                return [
                    'Check whether the term describes a compatibility need, model, size, material, system, or use case.',
                    'Review whether product data clearly explains that fitment or use case.',
                    'Add structured fitment, compatibility, or application data where useful.',
                    'Improve product/category copy so customers can connect the need to the right item.',
                    'Reindex Magento search and test the customer search again.',
                ];

            case 'Search term is too broad or unclear':
                return [
                    'Search the term manually and review the range of possible meanings.',
                    'Avoid forcing a narrow synonym or redirect unless intent is clear.',
                    'Improve categories, filters, suggested searches, and result ordering.',
                    'Use landing pages or category routing only where they genuinely help customers narrow intent.',
                    'Monitor repeated searches to see whether clearer patterns emerge.',
                ];

            case 'Results are weak or badly ranked':
                return [
                    'Search the term manually on the storefront.',
                    'Review whether the best products appear near the top.',
                    'Adjust searchable attributes, search weights, product data, ranking rules, or merchandising boosts.',
                    'Check whether irrelevant products are overpowering better matches.',
                    'Reindex Magento search and test the customer search again.',
                ];

            default:
                return [
                    'Search the term manually in Magento and on the storefront.',
                    'Review matching products, categories, synonyms, redirects, and searchable attributes.',
                    'Check whether this is a catalog gap or a findability issue.',
                    'Assign a clearer fix type after review.',
                    'Reindex Magento search data and test again.',
                ];
        }
    }

    private function getOpportunityScore(int $count, float $lostRevenue): string
    {
        if ($count >= 10 || $lostRevenue >= 300) {
            return 'High';
        }

        if ($count >= 3 || $lostRevenue >= 100) {
            return 'Medium';
        }

        return 'Low';
    }

    private function getConfidence(int $count, string $fixType): string
    {
        if ($count >= 10) {
            return 'High';
        }

        if ($count >= 3) {
            return 'Medium';
        }

        if (in_array($fixType, ['Part number mapping', 'Brand/product tagging', 'Synonym mapping'], true)) {
            return 'Medium';
        }

        return 'Low';
    }

    private function isNoiseSearchTerm(string $term): bool
    {
        $cleanTerm = strtolower(trim($term));

        if ($cleanTerm === '') {
            return true;
        }

        if (strlen($cleanTerm) < 3) {
            return true;
        }

        if (strlen($cleanTerm) > 120) {
            return true;
        }

        if (preg_match('/https?:\/\/|www\.|\.com|\.net|\.org|\.ru|\.cn|\.xyz/i', $cleanTerm)) {
            return true;
        }

        if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $cleanTerm)) {
            return true;
        }

        if (preg_match('/<script|<\/script|select\s+.*from|union\s+select|drop\s+table|insert\s+into|javascript:/i', $cleanTerm)) {
            return true;
        }

        $ignoredTerms = $this->parseCsvConfig($this->getConfiguredIgnoredTerms());

        foreach ($ignoredTerms as $ignoredTerm) {
            $normalizedIgnoredTerm = strtolower(trim($ignoredTerm));

            if ($normalizedIgnoredTerm !== '' && $cleanTerm === $normalizedIgnoredTerm) {
                return true;
            }
        }

        if (preg_match('/\b(testing|lorem|ipsum)\b/i', $cleanTerm)) {
            return true;
        }

        $lettersAndNumbers = preg_replace('/[^a-z0-9]/i', '', $cleanTerm);

        if (strlen($lettersAndNumbers) < 3) {
            return true;
        }

        $symbolCount = strlen(preg_replace('/[a-z0-9\s]/i', '', $cleanTerm));

        if ($symbolCount > 0 && $symbolCount >= strlen($cleanTerm) / 2) {
            return true;
        }

        if (preg_match('/(.)\1{5,}/', $cleanTerm)) {
            return true;
        }

        return false;
    }

    public function getFailedSearchTerms(string $period = 'all'): array
    {
        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from('search_query', ['query_text', 'num_results', 'popularity', 'updated_at'])
            ->where('num_results = 0')
            ->order('popularity DESC')
            ->limit(100);

        $this->applyDateFilter($select, $period);

        $minimumPopularity = $this->getConfiguredMinimumPopularity();

        $terms = array_values(array_filter(
            $connection->fetchAll($select),
            function ($term) use ($minimumPopularity) {
                return !$this->isNoiseSearchTerm((string)$term['query_text'])
                    && (int)$term['popularity'] >= $minimumPopularity;
            }
        ));

        $aov = $this->getAverageOrderValue();
        $conversionRate = $this->getConversionRate($period);

        foreach ($terms as &$term) {
            $term['lost_revenue'] = (float)$term['popularity'] * $aov * $conversionRate;
        }

        usort($terms, function ($a, $b) {
            return $b['lost_revenue'] <=> $a['lost_revenue'];
        });

        return array_slice($terms, 0, 20);
    }

    public function getWeakSearchTerms(string $period = 'all'): array
    {
        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from('search_query', ['query_text', 'num_results', 'popularity', 'updated_at'])
            ->where('num_results > 0')
            ->order('popularity DESC')
            ->limit(20);

        $this->applyDateFilter($select, $period);

        $terms = $connection->fetchAll($select);

        foreach ($terms as &$term) {
            $results = max((int)$term['num_results'], 1);
            $popularity = (int)$term['popularity'];

            $term['result_density'] = round($results / max($popularity, 1), 2);
            $term['opportunity_score'] = round($popularity / $results, 2);
        }

        usort($terms, function ($a, $b) {
            return $b['opportunity_score'] <=> $a['opportunity_score'];
        });

        return $terms;
    }

    public function getGa4FunnelTerms(): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll(
            $connection->select()
                ->from('scandiweb_searchloss_ga4_term')
                ->order('searches DESC')
                ->limit(20)
        );
    }

    public function getLowEngagementSearchTerms(): array
    {
        $connection = $this->resource->getConnection();

        $rows = $connection->fetchAll(
            $connection->select()
                ->from('scandiweb_searchloss_ga4_term', [
                    'search_term',
                    'searches' => new \Zend_Db_Expr('SUM(searches)'),
                    'product_views' => new \Zend_Db_Expr('SUM(product_views)'),
                    'add_to_carts' => new \Zend_Db_Expr('SUM(add_to_carts)'),
                    'purchases' => new \Zend_Db_Expr('SUM(purchases)'),
                    'revenue' => new \Zend_Db_Expr('SUM(revenue)'),
                    'latest_report_date' => new \Zend_Db_Expr('MAX(report_date)')
                ])
                ->group('search_term')
                ->order('searches DESC')
                ->limit(20)
        );

        $terms = [];

        foreach ($rows as $row) {
            $searches = max(1, (int)$row['searches']);
            $productViews = (int)$row['product_views'];
            $addToCarts = (int)$row['add_to_carts'];
            $purchases = (int)$row['purchases'];
            $revenue = (float)$row['revenue'];

            $productEngagementRate = round(($productViews / $searches) * 100, 2);
            $addToCartRate = round(($addToCarts / $searches) * 100, 2);
            $purchaseRate = round(($purchases / $searches) * 100, 2);

            $weaknessSignal = $this->getLowEngagementSignal(
                $searches,
                $productEngagementRate,
                $addToCartRate,
                $purchaseRate,
                $revenue
            );

            $terms[] = [
                'term' => (string)$row['search_term'],
                'searches' => $searches,
                'productViews' => $productViews,
                'addToCarts' => $addToCarts,
                'purchases' => $purchases,
                'revenue' => round($revenue, 2),
                'productEngagementRate' => $productEngagementRate,
                'addToCartRate' => $addToCartRate,
                'purchaseRate' => $purchaseRate,
                'weaknessSignal' => $weaknessSignal,
                'isLowEngagementFinding' => $this->isLowEngagementFinding($weaknessSignal),
                'diagnosis' => $this->getLowEngagementDiagnosis($weaknessSignal),
                'recommendedReview' => $this->getLowEngagementRecommendedReview($weaknessSignal),
            ];
        }

        return $terms;
    }

    private function getLowEngagementSignal(
        int $searches,
        float $productEngagementRate,
        float $addToCartRate,
        float $purchaseRate,
        float $revenue
    ): string {
        $minimumSearches = $this->getConfiguredLowEngagementMinimumSearches();
        $lowProductEngagementThreshold = $this->getConfiguredLowProductEngagementThreshold();
        $lowAddToCartThreshold = $this->getConfiguredLowAddToCartThreshold();
        $lowPurchaseThreshold = $this->getConfiguredLowPurchaseThreshold();
        $healthyPurchaseThreshold = $this->getConfiguredHealthyPurchaseThreshold();

        if ($revenue > 0 && $purchaseRate >= $healthyPurchaseThreshold) {
            return 'Healthy revenue signal';
        }

        if ($searches >= $minimumSearches && $productEngagementRate < $lowProductEngagementThreshold) {
            return 'High searches, low product engagement';
        }

        if ($productEngagementRate >= $lowProductEngagementThreshold && $addToCartRate < $lowAddToCartThreshold) {
            return 'Product views, low add-to-cart';
        }

        if ($addToCartRate >= $lowAddToCartThreshold && $purchaseRate < $lowPurchaseThreshold) {
            return 'Add-to-cart, low purchase';
        }

        return 'Needs review';
    }

    private function isLowEngagementFinding(string $weaknessSignal): bool
    {
        return in_array($weaknessSignal, [
            'High searches, low product engagement',
            'Product views, low add-to-cart',
            'Add-to-cart, low purchase',
        ], true);
    }

    private function getLowEngagementDiagnosis(string $weaknessSignal): string
    {
        switch ($weaknessSignal) {
            case 'High searches, low product engagement':
                return 'Customers search for this term, but do not appear to engage strongly with the returned products. Results may be irrelevant, unclear, poorly ranked, or difficult to refine.';

            case 'Product views, low add-to-cart':
                return 'Customers reach product pages after searching, but do not add to cart often. Review product detail quality, price, stock, fitment, confidence signals, and product-page clarity.';

            case 'Add-to-cart, low purchase':
                return 'Customers show buying intent after searching, but purchases remain low. Review checkout, shipping, account, pricing, availability, or quote-request friction.';

            case 'Healthy revenue signal':
                return 'This search term appears to drive some commercial value. It may not be a loss item, but it can still be monitored or optimized if strategically important.';

            default:
                return 'This search term has mixed engagement signals and should be reviewed manually before deciding whether it represents search friction.';
        }
    }

    private function getLowEngagementRecommendedReview(string $weaknessSignal): string
    {
        switch ($weaknessSignal) {
            case 'High searches, low product engagement':
                return 'Review search result relevance, ranking, filters, synonyms, product names, and whether the returned products match customer language.';

            case 'Product views, low add-to-cart':
                return 'Review product content, pricing, stock/salability, fitment information, images, delivery confidence, and product-page calls to action.';

            case 'Add-to-cart, low purchase':
                return 'Review checkout, shipping, payment, account requirements, quote flow, stock availability, and any purchase blockers after add-to-cart.';

            case 'Healthy revenue signal':
                return 'Monitor this term and consider optimizing ranking or merchandising if it is commercially important.';

            default:
                return 'Review GA4 tracking quality, search result quality, catalogue evidence, and whether this term needs a more specific diagnosis.';
        }
    }


    public function getLoggedInSearchIntelligence(): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('scandiweb_searchloss_search_event');

        if (!$connection->isTableExists($table)) {
            return [];
        }

        $customerTable = $this->resource->getTableName('customer_entity');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['se' => $table], [
                    'event_id',
                    'searched_at',
                    'completed_at',
                    'response_time_ms',
                    'completion_status',
                    'store_id',
                    'customer_id',
                    'search_term',
                    'results_count',
                    'source',
                ])
                ->joinLeft(
                    ['ce' => $customerTable],
                    'ce.entity_id = se.customer_id',
                    [
                        'customer_email' => 'email',
                        'customer_firstname' => 'firstname',
                        'customer_lastname' => 'lastname',
                    ]
                )
                ->order('se.searched_at DESC')
                ->limit(50)
        );

        $events = [];

        foreach ($rows as $row) {
            $status = $this->getLoggedInSearchLifecycleStatus($row);
            $followThrough = $this->getLoggedInSearchFollowThrough($row);
            $responseTimeMs = $row['response_time_ms'] === null ? null : (int)$row['response_time_ms'];
            $resultsCount = $row['results_count'] === null ? null : (int)$row['results_count'];

            $events[] = [
                'eventId' => (int)$row['event_id'],
                'searchedAt' => (string)$row['searched_at'],
                'completedAt' => $row['completed_at'] === null ? null : (string)$row['completed_at'],
                'responseTimeMs' => $responseTimeMs,
                'completionStatus' => (string)$row['completion_status'],
                'storeId' => (int)$row['store_id'],
                'customerId' => (int)$row['customer_id'],
                'customerEmail' => (string)($row['customer_email'] ?? ''),
                'customerName' => trim((string)($row['customer_firstname'] ?? '') . ' ' . (string)($row['customer_lastname'] ?? '')),
                'searchTerm' => (string)$row['search_term'],
                'resultsCount' => $resultsCount,
                'source' => (string)$row['source'],
                'lifecycleStatus' => $status['label'],
                'lifecycleExplanation' => $status['explanation'],
                'isPossibleSearchFriction' => $status['isPossibleSearchFriction'],
                'matchingCartFound' => $followThrough['matchingCartFound'],
                'matchingOrderFound' => $followThrough['matchingOrderFound'],
                'matchedItemName' => $followThrough['matchedItemName'],
                'matchedItemSku' => $followThrough['matchedItemSku'],
                'matchedQuoteId' => $followThrough['matchedQuoteId'],
                'matchedOrderId' => $followThrough['matchedOrderId'],
                'commercialFollowThroughStatus' => $followThrough['status'],
                'commercialFollowThroughExplanation' => $followThrough['explanation'],
                'isUnresolvedLoggedInSearch' => $followThrough['isUnresolvedLoggedInSearch'],
            ];
        }

        return $events;
    }


    private function getLoggedInSearchFollowThrough(array $row): array
    {
        $customerId = (int)($row['customer_id'] ?? 0);
        $searchTerm = trim((string)($row['search_term'] ?? ''));
        $searchedAt = (string)($row['searched_at'] ?? '');

        if ($customerId <= 0 || $searchTerm === '' || $searchedAt === '') {
            return [
                'matchingCartFound' => false,
                'matchingOrderFound' => false,
                'matchedItemName' => '',
                'matchedItemSku' => '',
                'matchedQuoteId' => null,
                'matchedOrderId' => null,
                'status' => 'Needs review',
                'explanation' => 'This logged-in search event could not be matched because customer, search term, or search timestamp was missing.',
                'isUnresolvedLoggedInSearch' => true,
            ];
        }

        $orderMatch = $this->findLoggedInSearchOrderMatch($customerId, $searchTerm, $searchedAt);

        if (!empty($orderMatch)) {
            return [
                'matchingCartFound' => false,
                'matchingOrderFound' => true,
                'matchedItemName' => (string)($orderMatch['name'] ?? ''),
                'matchedItemSku' => (string)($orderMatch['sku'] ?? ''),
                'matchedQuoteId' => null,
                'matchedOrderId' => (int)($orderMatch['order_id'] ?? 0),
                'status' => 'Matching order found',
                'explanation' => 'A later order item appears to match this logged-in search. This search likely had commercial follow-through.',
                'isUnresolvedLoggedInSearch' => false,
            ];
        }

        $cartMatch = $this->findLoggedInSearchCartMatch($customerId, $searchTerm, $searchedAt);

        if (!empty($cartMatch)) {
            return [
                'matchingCartFound' => true,
                'matchingOrderFound' => false,
                'matchedItemName' => (string)($cartMatch['name'] ?? ''),
                'matchedItemSku' => (string)($cartMatch['sku'] ?? ''),
                'matchedQuoteId' => (int)($cartMatch['quote_id'] ?? 0),
                'matchedOrderId' => null,
                'status' => 'Matching cart item found',
                'explanation' => 'A later cart item appears to match this logged-in search. This may indicate the customer continued toward purchase.',
                'isUnresolvedLoggedInSearch' => false,
            ];
        }

        return [
            'matchingCartFound' => false,
            'matchingOrderFound' => false,
            'matchedItemName' => '',
            'matchedItemSku' => '',
            'matchedQuoteId' => null,
            'matchedOrderId' => null,
            'status' => 'No later matching cart/order found',
            'explanation' => 'No later cart or order item clearly matched this logged-in search. This is a useful unresolved search-intent signal for review.',
            'isUnresolvedLoggedInSearch' => true,
        ];
    }

    private function findLoggedInSearchCartMatch(int $customerId, string $searchTerm, string $searchedAt): array
    {
        $connection = $this->resource->getConnection();

        $quoteTable = $this->resource->getTableName('quote');
        $quoteItemTable = $this->resource->getTableName('quote_item');

        if (!$connection->isTableExists($quoteTable) || !$connection->isTableExists($quoteItemTable)) {
            return [];
        }

        $conditions = $this->getLoggedInSearchItemMatchConditions('qi', $searchTerm);

        if (empty($conditions)) {
            return [];
        }

        $row = $connection->fetchRow(
            $connection->select()
                ->from(['q' => $quoteTable], ['quote_id' => 'entity_id'])
                ->join(
                    ['qi' => $quoteItemTable],
                    'qi.quote_id = q.entity_id',
                    ['sku', 'name', 'created_at', 'updated_at']
                )
                ->where('q.customer_id = ?', $customerId)
                ->where('qi.parent_item_id IS NULL')
                ->where('qi.created_at >= ?', $searchedAt)
                ->where('(' . implode(' OR ', $conditions) . ')')
                ->order('qi.created_at ASC')
                ->limit(1)
        );

        return is_array($row) ? $row : [];
    }

    private function findLoggedInSearchOrderMatch(int $customerId, string $searchTerm, string $searchedAt): array
    {
        $connection = $this->resource->getConnection();

        $orderTable = $this->resource->getTableName('sales_order');
        $orderItemTable = $this->resource->getTableName('sales_order_item');

        if (!$connection->isTableExists($orderTable) || !$connection->isTableExists($orderItemTable)) {
            return [];
        }

        $conditions = $this->getLoggedInSearchItemMatchConditions('soi', $searchTerm);

        if (empty($conditions)) {
            return [];
        }

        $row = $connection->fetchRow(
            $connection->select()
                ->from(['so' => $orderTable], ['order_id' => 'entity_id', 'increment_id'])
                ->join(
                    ['soi' => $orderItemTable],
                    'soi.order_id = so.entity_id',
                    ['sku', 'name', 'created_at']
                )
                ->where('so.customer_id = ?', $customerId)
                ->where('soi.parent_item_id IS NULL')
                ->where('so.created_at >= ?', $searchedAt)
                ->where('so.state != ?', 'canceled')
                ->where('(' . implode(' OR ', $conditions) . ')')
                ->order('so.created_at ASC')
                ->limit(1)
        );

        return is_array($row) ? $row : [];
    }

    private function getLoggedInSearchItemMatchConditions(string $alias, string $searchTerm): array
    {
        $connection = $this->resource->getConnection();

        $cleanTerm = trim((string)preg_replace('/\s+/', ' ', strtolower($searchTerm)));

        if ($cleanTerm === '') {
            return [];
        }

        /*
         * Logged-in Search Intelligence should stay customer-event-led.
         *
         * Match follow-through against the actual search phrase the logged-in
         * customer entered, not broad token/keyword matches. Broader keyword
         * matching belongs in catalogue evidence / failed-search diagnosis.
         */
        $like = '%' . $cleanTerm . '%';

        return [
            $connection->quoteInto('LOWER(' . $alias . '.name) LIKE ?', $like),
            $connection->quoteInto('LOWER(' . $alias . '.sku) LIKE ?', $like),
        ];
    }


    private function getLoggedInSearchLifecycleStatus(array $row): array
    {
        $completionStatus = (string)($row['completion_status'] ?? 'started');
        $completedAt = $row['completed_at'] ?? null;
        $responseTimeMs = $row['response_time_ms'] === null ? null : (int)$row['response_time_ms'];
        $resultsCount = $row['results_count'] === null ? null : (int)$row['results_count'];

        if ($completionStatus === 'started' && $completedAt === null) {
            return [
                'label' => 'Search started, completion not recorded',
                'explanation' => 'Magento received the logged-in customer search, but no completed server response was recorded. This may indicate search speed, timeout, cancellation, page failure, or another search-friction issue.',
                'isPossibleSearchFriction' => true,
            ];
        }

        if ($completionStatus === 'server_completed' && $resultsCount === 0) {
            return [
                'label' => 'Completed, zero results',
                'explanation' => 'Magento completed the search response, but returned zero results.',
                'isPossibleSearchFriction' => true,
            ];
        }

        if ($completionStatus === 'server_completed' && $responseTimeMs !== null && $responseTimeMs >= 3000) {
            return [
                'label' => 'Completed slowly',
                'explanation' => 'Magento completed the search response, but the response time was high enough to review as a possible search-speed issue.',
                'isPossibleSearchFriction' => true,
            ];
        }

        if ($completionStatus === 'server_completed') {
            return [
                'label' => 'Completed',
                'explanation' => 'Magento recorded a completed server response for this logged-in customer search.',
                'isPossibleSearchFriction' => false,
            ];
        }

        return [
            'label' => 'Needs review',
            'explanation' => 'This logged-in search event has an unexpected lifecycle state and should be reviewed.',
            'isPossibleSearchFriction' => true,
        ];
    }


    public function getOpportunityInsights(): array
    {
        $connection = $this->resource->getConnection();

        $rows = $connection->fetchAll(
            $connection->select()
                ->from('scandiweb_searchloss_ga4_term')
                ->order('searches DESC')
                ->limit(20)
        );

        $aov = $this->getAverageOrderValue();

        foreach ($rows as &$row) {
            $searches = (int)$row['searches'];
            $views = (int)$row['product_views'];
            $purchases = (int)$row['purchases'];

            $row['purchase_rate'] = $searches > 0 ? round(($purchases / $searches) * 100, 2) : 0;
            $row['missed_purchases'] = max($searches - $purchases, 0);
            $row['estimated_missed_revenue'] = $row['missed_purchases'] * $aov;

            if ($views === 0) {
                $row['issue'] = 'Zero Results';
            } elseif ($purchases === 0) {
                $row['issue'] = 'Weak Results';
            } elseif ($row['purchase_rate'] < 5) {
                $row['issue'] = 'Low Conversion';
            } else {
                $row['issue'] = 'OK';
            }
        }

        usort($rows, function ($a, $b) {
            return $b['estimated_missed_revenue'] <=> $a['estimated_missed_revenue'];
        });

        return $rows;
    }

    public function getAllFailedSearchReportTerms(string $period = 'all'): array
    {
        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from('search_query', ['query_text', 'num_results', 'popularity', 'updated_at'])
            ->where('num_results = 0')
            ->order('popularity DESC');

        $this->applyDateFilter($select, $period);

        $minimumPopularity = $this->getConfiguredMinimumPopularity();

        $terms = array_values(array_filter(
            $connection->fetchAll($select),
            function ($term) use ($minimumPopularity) {
                return !$this->isNoiseSearchTerm((string)$term['query_text'])
                    && (int)$term['popularity'] >= $minimumPopularity;
            }
        ));

        $aov = $this->getAverageOrderValue();
        $conversionRate = $this->getConversionRate($period);

        foreach ($terms as &$term) {
            $termText = (string)$term['query_text'];
            $count = (int)$term['popularity'];
            $estimatedDemandValue = round((float)$count * $aov * $conversionRate, 2);
            $fixType = $this->getFixType($termText);

            $term['lost_revenue'] = $estimatedDemandValue;
            $term['report_row'] = [
                'term' => $termText,
                'count' => $count,
                'estimatedDemandValue' => $estimatedDemandValue,
                'priority' => $this->getOpportunityScore($count, $estimatedDemandValue),
                'issueType' => $fixType,
                'fixEffortBucket' => $this->getFixEffortBucket($fixType),
                'confidence' => $this->getConfidence($count, $fixType),
                'suggestedAction' => $this->getShortSuggestedAction($termText, $fixType),
                'fullRecommendation' => $this->getSuggestedFix($termText, $fixType),
                'magentoFixSteps' => implode(' | ', $this->getMagentoFixSteps($termText, $fixType)),
                'lastSearched' => (string)($term['updated_at'] ?? ''),
            ];
        }

        usort($terms, function ($a, $b) {
            return $b['lost_revenue'] <=> $a['lost_revenue'];
        });

        return array_map(function ($term) {
            return $term['report_row'];
        }, $terms);
    }

    public function getSummary(string $period = 'all'): array
    {
        $failed = $this->getFailedSearchTerms($period);

        $totalFailedSearches = 0;
        $totalLostRevenue = 0;

        foreach ($failed as $term) {
            $totalFailedSearches += (int)$term['popularity'];
            $totalLostRevenue += (float)$term['lost_revenue'];
        }

        return [
            'failed_terms' => count($failed),
            'failed_searches' => $totalFailedSearches,
            'lost_revenue' => $totalLostRevenue,
            'aov' => $this->getAverageOrderValue(),
            'conversion_rate' => $this->getConversionRate($period) * 100,
        ];
    }

    public function getDashboardData(string $period = 'all'): array
    {
        $summary = $this->getSummary($period);
        $failedTerms = $this->getFailedSearchTerms($period);

        $totalSearches = $this->getTotalSearches($period);
        $failedSearches = $this->getFailedSearchCount($period);
        $zeroResultRate = $totalSearches > 0 ? ($failedSearches / $totalSearches) * 100 : 0;

        $externalTerms = [];

        foreach ($failedTerms as $term) {
            $termText = (string)$term['query_text'];
            $count = (int)$term['popularity'];
            $lostRevenue = round((float)$term['lost_revenue'], 2);
            $fixType = $this->getFixType($termText);

            $externalTerms[] = [
                'term' => $termText,
                'count' => $count,
                'conversion' => 0,
                'lostRevenue' => $lostRevenue,
                'opportunityScore' => $this->getOpportunityScore($count, $lostRevenue),
                'fixType' => $fixType,
                'fixEffortBucket' => $this->getFixEffortBucket($fixType),
                'suggestedFix' => $this->getSuggestedFix($termText, $fixType),
                'shortSuggestedAction' => $this->getShortSuggestedAction($termText, $fixType),
                'plainEnglishMeaning' => $this->getPlainEnglishMeaning($termText, $fixType),
                'magentoFixSteps' => $this->getMagentoFixSteps($termText, $fixType),
                'catalogEvidence' => $this->getCatalogEvidence($termText),
                'confidence' => $this->getConfidence($count, $fixType),
                'trend' => 'up'
            ];
        }

        return [
            [
                'key' => 'searchData',
                'value' => [
                    'totalSearches' => $totalSearches,
                    'failedSearches' => $failedSearches,
                    'zeroResultRate' => round($zeroResultRate, 2),
                    'searchToOrderRate' => round($summary['conversion_rate'], 2),
                    'averageOrderValue' => round($summary['aov'], 2),
                    'modeledDemandLost' => round($summary['lost_revenue'], 2)
                ]
            ],
            [
                'key' => 'failedSearchTerms',
                'value' => $externalTerms
            ],
            [
                'key' => 'lowEngagementSearchTerms',
                'value' => $this->getLowEngagementSearchTerms()
            ],
            [
                'key' => 'loggedInSearchIntelligence',
                'value' => $this->getLoggedInSearchIntelligence()
            ]
        ];
    }
}
