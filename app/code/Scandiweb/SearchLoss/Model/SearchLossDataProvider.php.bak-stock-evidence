<?php

namespace Scandiweb\SearchLoss\Model;

use Magento\Framework\App\ResourceConnection;

class SearchLossDataProvider
{
    protected ResourceConnection $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
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

    private function getProductVisibilitySignals(string $term, array $tokens): array
    {
        $connection = $this->resource->getConnection();

        $productEntityTable = $this->resource->getTableName('catalog_product_entity');
        $productVarcharTable = $this->resource->getTableName('catalog_product_entity_varchar');
        $productIntTable = $this->resource->getTableName('catalog_product_entity_int');
        $productWebsiteTable = $this->resource->getTableName('catalog_product_website');
        $productCategoryTable = $this->resource->getTableName('catalog_category_product');

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
            ];
        }

        $enabledProducts = 0;
        $disabledProducts = 0;
        $visibleInSearch = 0;
        $notVisibleInSearch = 0;

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
            $suggestion = 'A SKU-like match exists, but search still failed. Review SKU search behavior, indexing, and searchable attributes.';
        } elseif ((int)$signals['productNameMatches'] > 0 || (int)$signals['categoryNameMatches'] > 0) {
            $status = 'Exact catalog wording found';
            $suggestion = 'Magento has matching catalog wording, but search still failed. Review indexing, searchable attributes, synonyms, and result ranking.';
        } elseif ($relatedProductMatches > 0 || $relatedCategoryMatches > 0) {
            $status = 'Related catalog wording found';
            $suggestion = 'Magento has related catalog wording, but the customer search still failed. This usually means product naming, searchable attributes, synonyms, or search ranking need review.';
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

            if ((int)$visibilitySignals['visibleInSearch'] > 0) {
                return 'Product exists but search is not matching it';
            }

            return 'Product exists but is not showing';
        }

        if ($catalogueSignals['hasSkuMatch']) {
            return 'SKU or part number is not matching';
        }

        if ($catalogueSignals['hasProductNameMatch'] || $catalogueSignals['hasCategoryNameMatch']) {
            return 'Product exists but search is not matching it';
        }

        if (preg_match('/[a-z]*\d+[a-z\d\-\.]*/i', $term)) {
            return 'SKU or part number is not matching';
        }

        if (preg_match('/nano\s+lea\.?f|lea\.f|sprng|suspention|bushng|galvani[sz]ed/i', $normalized)) {
            return 'Spelling or format variant';
        }

        if (preg_match('/hendrickson|dexter|al-ko|lippert|bpw|meritor|febi|saf/i', $normalized)) {
            return 'Brand or product terms are missing';
        }

        if (preg_match('/air\s*bag|airbag|air\s*spring|air\s*suspension/i', $normalized)) {
            return 'Customers use different wording';
        }

        if (preg_match('/\b(2016|2017|2018|2019|2020|2021|2022|2023|2024|2025|2026)\b/i', $normalized)) {
            return 'Fitment or use case is unclear';
        }

        if (preg_match('/\b(axle|spring|suspension|brake|hub|bushing|bolt|nut|seal|kit|shackle|equalizer|bearing|plate)\b/i', $normalized)) {
            return 'Product or category may be missing';
        }

        if (str_word_count($normalized) >= 3) {
            return 'Fitment or use case is unclear';
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

        if (preg_match('/\b(test|testing|asdf|qwerty|lorem|ipsum|null|undefined)\b/i', $cleanTerm)) {
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

        $terms = array_values(array_filter(
            $connection->fetchAll($select),
            function ($term) {
                return !$this->isNoiseSearchTerm((string)$term['query_text']);
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
            ]
        ];
    }
}