<?php

namespace Scandiweb\SearchLoss\Model;

use Magento\Framework\App\ResourceConnection;

class SearchLossDataProvider
{
    private const SEARCH_EVENT_TABLE = 'scandiweb_searchloss_search_event';

    private array $customerAverageItemValueCache = [];
    private ?float $storeAverageItemValue = null;

    public function __construct(
        private ResourceConnection $resource
    ) {}

    public function getDashboardData(): array
    {
        $events = $this->getLoggedInSearchIntelligence();

        return [
            [
                'key' => 'summary',
                'value' => $this->getSummary($events),
            ],
            [
                'key' => 'loggedInSearchIntelligence',
                'value' => $events,
            ],
        ];
    }

    public function getLoggedInSearchIntelligence(): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::SEARCH_EVENT_TABLE);

        if (!$connection->isTableExists($table)) {
            return [];
        }

        $customerTable = $this->resource->getTableName('customer_entity');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['se' => $table])
                ->joinLeft(
                    ['ce' => $customerTable],
                    'ce.entity_id = se.customer_id',
                    [
                        'customer_email' => 'email',
                        'customer_firstname' => 'firstname',
                        'customer_lastname' => 'lastname',
                    ]
                )
                ->order('se.event_id DESC')
                ->limit(500)
        );

        $events = [];

        foreach ($rows as $row) {
            $customerId = (int)$row['customer_id'];
            $searchTerm = (string)$row['search_term'];

            $lifecycle = $this->getLifecycleStatus($row);
            $followThrough = $this->getCommercialFollowThrough($customerId, $searchTerm, (string)$row['searched_at']);

            $event = [
                'eventId' => (int)$row['event_id'],
                'searchedAt' => (string)$row['searched_at'],
                'completedAt' => $row['completed_at'] === null ? null : (string)$row['completed_at'],
                'responseTimeMs' => $row['response_time_ms'] === null ? null : (int)$row['response_time_ms'],
                'completionStatus' => (string)$row['completion_status'],
                'storeId' => (int)$row['store_id'],
                'customerId' => $customerId,
                'customerEmail' => (string)($row['customer_email'] ?? ''),
                'customerName' => trim((string)($row['customer_firstname'] ?? '') . ' ' . (string)($row['customer_lastname'] ?? '')),
                'searchTerm' => $searchTerm,
                'resultsCount' => $row['results_count'] === null ? null : (int)$row['results_count'],
                'source' => (string)$row['source'],
                'lifecycleStatus' => $lifecycle['status'],
                'lifecycleExplanation' => $lifecycle['explanation'],
                'commercialFollowThroughStatus' => $followThrough['status'],
                'commercialFollowThroughExplanation' => $followThrough['explanation'],
                'matchedItemName' => $followThrough['matchedItemName'],
                'matchedItemSku' => $followThrough['matchedItemSku'],
                'matchedItemType' => $followThrough['matchedItemType'],
            ];

            $isUnresolved = $this->isUnresolvedLoggedInSearch($event);
            $customerAverageItemValue = $this->getCustomerAverageItemValue($customerId);
            $potentialValue = $isUnresolved ? $customerAverageItemValue : 0.0;

            $event['isUnresolvedLoggedInSearch'] = $isUnresolved;
            $event['customerAverageItemValue'] = round($customerAverageItemValue, 2);
            $event['customerAverageItemValueFormatted'] = $this->formatCurrency($customerAverageItemValue);
            $event['potentialValue'] = round($potentialValue, 2);
            $event['potentialValueFormatted'] = $potentialValue > 0 ? $this->formatCurrency($potentialValue) : '-';

            $events[] = $event;
        }

        return $events;
    }

    public function getSummary(array $events): array
    {
        $summary = [
            'totalSearches' => count($events),
            'unresolvedSearches' => 0,
            'completedSearches' => 0,
            'completionNotRecorded' => 0,
            'zeroResultSearches' => 0,
            'noLaterMatchingCartOrOrder' => 0,
            'matchedCartItems' => 0,
            'matchedOrders' => 0,
            'potentialValue' => 0.0,
            'potentialValueFormatted' => '$0',
        ];

        $potentialValueKeys = [];

        foreach ($events as $event) {
            if (($event['completedAt'] ?? null) === null) {
                $summary['completionNotRecorded']++;
            } else {
                $summary['completedSearches']++;
            }

            if (($event['resultsCount'] ?? null) === 0) {
                $summary['zeroResultSearches']++;
            }

            $followThrough = strtolower((string)($event['commercialFollowThroughStatus'] ?? ''));

            if (str_contains($followThrough, 'matching cart item')) {
                $summary['matchedCartItems']++;
            }

            if (str_contains($followThrough, 'matching order item')) {
                $summary['matchedOrders']++;
            }

            if (str_contains($followThrough, 'no later matching')) {
                $summary['noLaterMatchingCartOrOrder']++;
            }

            if (!empty($event['isUnresolvedLoggedInSearch'])) {
                $summary['unresolvedSearches']++;

                $key = (int)($event['customerId'] ?? 0) . '|' . strtolower(trim((string)($event['searchTerm'] ?? '')));

                if (!isset($potentialValueKeys[$key])) {
                    $summary['potentialValue'] += (float)($event['potentialValue'] ?? 0);
                    $potentialValueKeys[$key] = true;
                }
            }
        }

        $summary['potentialValue'] = round($summary['potentialValue'], 2);
        $summary['potentialValueFormatted'] = $this->formatCurrency($summary['potentialValue']);

        return $summary;
    }

    public function getTopSearchIntelligenceActions(array $events): array
    {
        $summary = $this->getSummary($events);

        return [
            [
                'label' => 'Potential value',
                'count' => (int)round((float)$summary['potentialValue']),
                'displayValue' => $summary['potentialValueFormatted'],
            ],
            [
                'label' => 'Potential incomplete searches',
                'count' => (int)$summary['completionNotRecorded'],
            ],
            [
                'label' => 'Zero-result customer searches',
                'count' => (int)$summary['zeroResultSearches'],
            ],
            [
                'label' => 'No matching cart or order',
                'count' => (int)$summary['noLaterMatchingCartOrOrder'],
            ],
            [
                'label' => 'Searches leading to cart',
                'count' => (int)$summary['matchedCartItems'],
            ],
            [
                'label' => 'Searches leading to orders',
                'count' => (int)$summary['matchedOrders'],
            ],
        ];
    }

    private function getLifecycleStatus(array $row): array
    {
        if (($row['completed_at'] ?? null) === null || (string)($row['completion_status'] ?? '') !== 'server_completed') {
            return [
                'status' => 'Search started, completion not recorded',
                'explanation' => 'Magento received the logged-in customer search, but no completed server response was recorded.',
            ];
        }

        $resultsCount = $row['results_count'] === null ? null : (int)$row['results_count'];

        if ($resultsCount === 0) {
            return [
                'status' => 'Completed, zero results',
                'explanation' => 'Magento completed the search response, but returned zero results.',
            ];
        }

        return [
            'status' => 'Completed',
            'explanation' => 'Magento recorded a completed server response for this logged-in customer search.',
        ];
    }

    private function getCommercialFollowThrough(int $customerId, string $searchTerm, string $searchedAt): array
    {
        if ($customerId <= 0 || trim($searchTerm) === '') {
            return $this->noFollowThrough();
        }

        $orderMatch = $this->findMatchingOrderItem($customerId, $searchTerm, $searchedAt);

        if ($orderMatch !== null) {
            return [
                'status' => 'Matching order item found',
                'explanation' => 'A later order item appears to match this logged-in customer search.',
                'matchedItemName' => (string)($orderMatch['name'] ?? ''),
                'matchedItemSku' => (string)($orderMatch['sku'] ?? ''),
                'matchedItemType' => 'order',
            ];
        }

        $cartMatch = $this->findMatchingCartItem($customerId, $searchTerm, $searchedAt);

        if ($cartMatch !== null) {
            return [
                'status' => 'Matching cart item found',
                'explanation' => 'A later cart item appears to match this logged-in customer search.',
                'matchedItemName' => (string)($cartMatch['name'] ?? ''),
                'matchedItemSku' => (string)($cartMatch['sku'] ?? ''),
                'matchedItemType' => 'cart',
            ];
        }

        return $this->noFollowThrough();
    }

    private function noFollowThrough(): array
    {
        return [
            'status' => 'No later matching cart/order found',
            'explanation' => 'No later cart or order item clearly matched this logged-in search.',
            'matchedItemName' => '',
            'matchedItemSku' => '',
            'matchedItemType' => '',
        ];
    }

    private function findMatchingCartItem(int $customerId, string $searchTerm, string $searchedAt): ?array
    {
        $connection = $this->resource->getConnection();
        $quoteTable = $this->resource->getTableName('quote');
        $quoteItemTable = $this->resource->getTableName('quote_item');

        if (!$connection->isTableExists($quoteTable) || !$connection->isTableExists($quoteItemTable)) {
            return null;
        }

        $matchCondition = $this->getItemMatchCondition('qi', $searchTerm);

        if ($matchCondition === null) {
            return null;
        }

        $row = $connection->fetchRow(
            $connection->select()
                ->from(['qi' => $quoteItemTable], ['name', 'sku'])
                ->joinInner(['q' => $quoteTable], 'q.entity_id = qi.quote_id', [])
                ->where('q.customer_id = ?', $customerId)
                ->where('qi.parent_item_id IS NULL')
                ->where('qi.created_at >= ?', $searchedAt)
                ->where($matchCondition)
                ->order('qi.created_at ASC')
                ->limit(1)
        );

        return $row ?: null;
    }

    private function findMatchingOrderItem(int $customerId, string $searchTerm, string $searchedAt): ?array
    {
        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $orderItemTable = $this->resource->getTableName('sales_order_item');

        if (!$connection->isTableExists($orderTable) || !$connection->isTableExists($orderItemTable)) {
            return null;
        }

        $matchCondition = $this->getItemMatchCondition('soi', $searchTerm);

        if ($matchCondition === null) {
            return null;
        }

        $row = $connection->fetchRow(
            $connection->select()
                ->from(['soi' => $orderItemTable], ['name', 'sku'])
                ->joinInner(['so' => $orderTable], 'so.entity_id = soi.order_id', [])
                ->where('so.customer_id = ?', $customerId)
                ->where('so.state NOT IN (?)', ['canceled'])
                ->where('soi.parent_item_id IS NULL')
                ->where('so.created_at >= ?', $searchedAt)
                ->where($matchCondition)
                ->order('so.created_at ASC')
                ->limit(1)
        );

        return $row ?: null;
    }

    private function getItemMatchCondition(string $alias, string $searchTerm): ?string
    {
        $connection = $this->resource->getConnection();
        $cleanTerm = trim((string)preg_replace('/\s+/', ' ', strtolower($searchTerm)));

        if ($cleanTerm === '') {
            return null;
        }

        $like = '%' . $cleanTerm . '%';

        return '('
            . $connection->quoteInto('LOWER(' . $alias . '.name) LIKE ?', $like)
            . ' OR '
            . $connection->quoteInto('LOWER(' . $alias . '.sku) LIKE ?', $like)
            . ')';
    }

    private function isUnresolvedLoggedInSearch(array $event): bool
    {
        if (($event['completedAt'] ?? null) === null) {
            return true;
        }

        if (($event['resultsCount'] ?? null) === 0) {
            return true;
        }

        $followThrough = strtolower((string)($event['commercialFollowThroughStatus'] ?? ''));

        return str_contains($followThrough, 'no later matching');
    }

    private function getCustomerAverageItemValue(int $customerId): float
    {
        if ($customerId <= 0) {
            return $this->getStoreAverageItemValue();
        }

        if (array_key_exists($customerId, $this->customerAverageItemValueCache)) {
            return $this->customerAverageItemValueCache[$customerId];
        }

        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $orderItemTable = $this->resource->getTableName('sales_order_item');

        if (!$connection->isTableExists($orderTable) || !$connection->isTableExists($orderItemTable)) {
            $this->customerAverageItemValueCache[$customerId] = 0.0;
            return 0.0;
        }

        $revenueExpression = $this->getRevenueExpression('soi', $orderItemTable, 'qty_ordered');

        $row = $connection->fetchRow(
            $connection->select()
                ->from(
                    ['soi' => $orderItemTable],
                    [
                        'revenue' => new \Zend_Db_Expr('SUM(' . $revenueExpression . ')'),
                        'qty' => new \Zend_Db_Expr('SUM(COALESCE(soi.qty_ordered, 0))'),
                    ]
                )
                ->joinInner(['so' => $orderTable], 'so.entity_id = soi.order_id', [])
                ->where('so.customer_id = ?', $customerId)
                ->where('so.state NOT IN (?)', ['canceled'])
                ->where('soi.parent_item_id IS NULL')
        );

        $revenue = (float)($row['revenue'] ?? 0);
        $qty = (float)($row['qty'] ?? 0);

        if ($revenue > 0 && $qty > 0) {
            $value = $revenue / $qty;
            $this->customerAverageItemValueCache[$customerId] = $value;
            return $value;
        }

        $cartFallback = $this->getCustomerAverageCartItemValue($customerId);

        if ($cartFallback > 0) {
            $this->customerAverageItemValueCache[$customerId] = $cartFallback;
            return $cartFallback;
        }

        $storeFallback = $this->getStoreAverageItemValue();
        $this->customerAverageItemValueCache[$customerId] = $storeFallback;

        return $storeFallback;
    }

    private function getCustomerAverageCartItemValue(int $customerId): float
    {
        $connection = $this->resource->getConnection();
        $quoteTable = $this->resource->getTableName('quote');
        $quoteItemTable = $this->resource->getTableName('quote_item');

        if (!$connection->isTableExists($quoteTable) || !$connection->isTableExists($quoteItemTable)) {
            return 0.0;
        }

        $revenueExpression = $this->getRevenueExpression('qi', $quoteItemTable, 'qty');

        $row = $connection->fetchRow(
            $connection->select()
                ->from(
                    ['qi' => $quoteItemTable],
                    [
                        'revenue' => new \Zend_Db_Expr('SUM(' . $revenueExpression . ')'),
                        'qty' => new \Zend_Db_Expr('SUM(COALESCE(qi.qty, 0))'),
                    ]
                )
                ->joinInner(['q' => $quoteTable], 'q.entity_id = qi.quote_id', [])
                ->where('q.customer_id = ?', $customerId)
                ->where('qi.parent_item_id IS NULL')
        );

        $revenue = (float)($row['revenue'] ?? 0);
        $qty = (float)($row['qty'] ?? 0);

        return $revenue > 0 && $qty > 0 ? $revenue / $qty : 0.0;
    }

    private function getStoreAverageItemValue(): float
    {
        if ($this->storeAverageItemValue !== null) {
            return $this->storeAverageItemValue;
        }

        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $orderItemTable = $this->resource->getTableName('sales_order_item');

        if (!$connection->isTableExists($orderTable) || !$connection->isTableExists($orderItemTable)) {
            $this->storeAverageItemValue = 0.0;
            return 0.0;
        }

        $revenueExpression = $this->getRevenueExpression('soi', $orderItemTable, 'qty_ordered');

        $row = $connection->fetchRow(
            $connection->select()
                ->from(
                    ['soi' => $orderItemTable],
                    [
                        'revenue' => new \Zend_Db_Expr('SUM(' . $revenueExpression . ')'),
                        'qty' => new \Zend_Db_Expr('SUM(COALESCE(soi.qty_ordered, 0))'),
                    ]
                )
                ->joinInner(['so' => $orderTable], 'so.entity_id = soi.order_id', [])
                ->where('so.state NOT IN (?)', ['canceled'])
                ->where('soi.parent_item_id IS NULL')
        );

        $revenue = (float)($row['revenue'] ?? 0);
        $qty = (float)($row['qty'] ?? 0);

        $this->storeAverageItemValue = $revenue > 0 && $qty > 0 ? $revenue / $qty : 0.0;

        return $this->storeAverageItemValue;
    }

    private function getRevenueExpression(string $alias, string $table, string $qtyColumn): string
    {
        $connection = $this->resource->getConnection();

        if ($connection->tableColumnExists($table, 'base_row_total')) {
            return 'COALESCE(' . $alias . '.base_row_total, 0)';
        }

        if ($connection->tableColumnExists($table, 'row_total')) {
            return 'COALESCE(' . $alias . '.row_total, 0)';
        }

        if ($connection->tableColumnExists($table, 'base_price')) {
            return 'COALESCE(' . $alias . '.base_price, 0) * COALESCE(' . $alias . '.' . $qtyColumn . ', 0)';
        }

        if ($connection->tableColumnExists($table, 'price')) {
            return 'COALESCE(' . $alias . '.price, 0) * COALESCE(' . $alias . '.' . $qtyColumn . ', 0)';
        }

        return '0';
    }

    private function formatCurrency(float $value): string
    {
        return '$' . number_format($value, 0);
    }
}
