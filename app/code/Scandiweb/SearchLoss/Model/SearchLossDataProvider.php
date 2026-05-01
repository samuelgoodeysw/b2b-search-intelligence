<?php

namespace Scandiweb\SearchLoss\Model;

use Magento\Framework\App\ResourceConnection;

class SearchLossDataProvider
{
    private const SEARCH_EVENT_TABLE = 'scandiweb_searchloss_search_event';

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

    public function getSummary(array $events = []): array
    {
        if (empty($events)) {
            $events = $this->getLoggedInSearchIntelligence();
        }

        $summary = [
            'totalSearches' => count($events),
            'unresolvedSearches' => 0,
            'completedSearches' => 0,
            'completionNotRecorded' => 0,
            'matchedCartItems' => 0,
            'matchedOrders' => 0,
        ];

        foreach ($events as $event) {
            if (!empty($event['isUnresolvedLoggedInSearch'])) {
                $summary['unresolvedSearches']++;
            }

            if (($event['completionStatus'] ?? '') === 'server_completed') {
                $summary['completedSearches']++;
            }

            if (($event['completionStatus'] ?? '') === 'started') {
                $summary['completionNotRecorded']++;
            }

            if (!empty($event['matchingCartFound'])) {
                $summary['matchedCartItems']++;
            }

            if (!empty($event['matchingOrderFound'])) {
                $summary['matchedOrders']++;
            }
        }

        return $summary;
    }

    public function getTopSearchIntelligenceActions(array $events = []): array
    {
        if (empty($events)) {
            $events = $this->getLoggedInSearchIntelligence();
        }

        $actions = [
            'completion_not_recorded' => [
                'label' => 'Searches that may not have completed',
                'count' => 0,
                'priority' => 'High',
                'summary' => 'Magento received the search request, but no completed response was recorded.',
            ],
            'zero_results' => [
                'label' => 'Completed searches with zero results',
                'count' => 0,
                'priority' => 'High',
                'summary' => 'The customer searched while logged in, Magento completed the response, but returned zero results.',
            ],
            'slow_searches' => [
                'label' => 'Slow completed searches',
                'count' => 0,
                'priority' => 'Medium',
                'summary' => 'Magento completed the search response, but server-side response time was high.',
            ],
            'unresolved_intent' => [
                'label' => 'No later matching cart or order',
                'count' => 0,
                'priority' => 'High',
                'summary' => 'No later cart or order item clearly matched the logged-in customer search.',
            ],
            'cart_followthrough' => [
                'label' => 'Searches that led to cart activity',
                'count' => 0,
                'priority' => 'Positive signal',
                'summary' => 'A later cart item appears to match the logged-in customer search.',
            ],
            'order_followthrough' => [
                'label' => 'Searches that led to orders',
                'count' => 0,
                'priority' => 'Positive signal',
                'summary' => 'A later order item appears to match the logged-in customer search.',
            ],
        ];

        foreach ($events as $event) {
            $lifecycle = (string)($event['lifecycleStatus'] ?? '');
            $followThrough = (string)($event['commercialFollowThroughStatus'] ?? '');

            if (str_contains($lifecycle, 'completion not recorded')) {
                $actions['completion_not_recorded']['count']++;
            }

            if (str_contains($lifecycle, 'zero results')) {
                $actions['zero_results']['count']++;
            }

            if (str_contains($lifecycle, 'slow')) {
                $actions['slow_searches']['count']++;
            }

            if (!empty($event['isUnresolvedLoggedInSearch'])) {
                $actions['unresolved_intent']['count']++;
            }

            if (str_contains($followThrough, 'cart item')) {
                $actions['cart_followthrough']['count']++;
            }

            if (str_contains($followThrough, 'order')) {
                $actions['order_followthrough']['count']++;
            }
        }

        return array_values(array_filter($actions, static function (array $action): bool {
            return (int)$action['count'] > 0;
        }));
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
                ->limit(100)
        );

        $events = [];

        foreach ($rows as $row) {
            $status = $this->getLifecycleStatus($row);
            $followThrough = $this->getFollowThrough($row);

            $events[] = [
                'eventId' => (int)$row['event_id'],
                'searchedAt' => (string)$row['searched_at'],
                'completedAt' => $row['completed_at'] === null ? null : (string)$row['completed_at'],
                'responseTimeMs' => $row['response_time_ms'] === null ? null : (int)$row['response_time_ms'],
                'completionStatus' => (string)$row['completion_status'],
                'storeId' => (int)$row['store_id'],
                'customerId' => (int)$row['customer_id'],
                'customerEmail' => (string)($row['customer_email'] ?? ''),
                'customerName' => trim((string)($row['customer_firstname'] ?? '') . ' ' . (string)($row['customer_lastname'] ?? '')),
                'searchTerm' => (string)$row['search_term'],
                'resultsCount' => $row['results_count'] === null ? null : (int)$row['results_count'],
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

    private function getLifecycleStatus(array $row): array
    {
        $completionStatus = (string)($row['completion_status'] ?? 'started');
        $completedAt = $row['completed_at'] ?? null;
        $responseTimeMs = $row['response_time_ms'] === null ? null : (int)$row['response_time_ms'];
        $resultsCount = $row['results_count'] === null ? null : (int)$row['results_count'];

        if ($completionStatus === 'started' && $completedAt === null) {
            return [
                'label' => 'Search started, completion not recorded',
                'explanation' => 'Magento received the logged-in customer search, but no completed server response was recorded.',
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
                'explanation' => 'Magento completed the search response, but the server-side response time was high.',
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
            'explanation' => 'This logged-in search event has an unexpected lifecycle state.',
            'isPossibleSearchFriction' => true,
        ];
    }

    private function getFollowThrough(array $row): array
    {
        $customerId = (int)($row['customer_id'] ?? 0);
        $searchTerm = trim((string)($row['search_term'] ?? ''));
        $searchedAt = (string)($row['searched_at'] ?? '');

        if ($customerId <= 0 || $searchTerm === '' || $searchedAt === '') {
            return $this->emptyFollowThrough('Needs review', 'Customer, search term, or timestamp was missing.', true);
        }

        $orderMatch = $this->findOrderMatch($customerId, $searchTerm, $searchedAt);

        if (!empty($orderMatch)) {
            return [
                'matchingCartFound' => false,
                'matchingOrderFound' => true,
                'matchedItemName' => (string)($orderMatch['name'] ?? ''),
                'matchedItemSku' => (string)($orderMatch['sku'] ?? ''),
                'matchedQuoteId' => null,
                'matchedOrderId' => (int)($orderMatch['order_id'] ?? 0),
                'status' => 'Matching order found',
                'explanation' => 'A later order item appears to match this logged-in search.',
                'isUnresolvedLoggedInSearch' => false,
            ];
        }

        $cartMatch = $this->findCartMatch($customerId, $searchTerm, $searchedAt);

        if (!empty($cartMatch)) {
            return [
                'matchingCartFound' => true,
                'matchingOrderFound' => false,
                'matchedItemName' => (string)($cartMatch['name'] ?? ''),
                'matchedItemSku' => (string)($cartMatch['sku'] ?? ''),
                'matchedQuoteId' => (int)($cartMatch['quote_id'] ?? 0),
                'matchedOrderId' => null,
                'status' => 'Matching cart item found',
                'explanation' => 'A later cart item appears to match this logged-in search.',
                'isUnresolvedLoggedInSearch' => false,
            ];
        }

        return $this->emptyFollowThrough(
            'No later matching cart/order found',
            'No later cart or order item clearly matched this logged-in search.',
            true
        );
    }

    private function emptyFollowThrough(string $status, string $explanation, bool $unresolved): array
    {
        return [
            'matchingCartFound' => false,
            'matchingOrderFound' => false,
            'matchedItemName' => '',
            'matchedItemSku' => '',
            'matchedQuoteId' => null,
            'matchedOrderId' => null,
            'status' => $status,
            'explanation' => $explanation,
            'isUnresolvedLoggedInSearch' => $unresolved,
        ];
    }

    private function findCartMatch(int $customerId, string $searchTerm, string $searchedAt): array
    {
        $connection = $this->resource->getConnection();
        $quoteTable = $this->resource->getTableName('quote');
        $quoteItemTable = $this->resource->getTableName('quote_item');

        if (!$connection->isTableExists($quoteTable) || !$connection->isTableExists($quoteItemTable)) {
            return [];
        }

        $conditions = $this->getItemMatchConditions('qi', $searchTerm);

        if (empty($conditions)) {
            return [];
        }

        $row = $connection->fetchRow(
            $connection->select()
                ->from(['q' => $quoteTable], ['quote_id' => 'entity_id'])
                ->join(['qi' => $quoteItemTable], 'qi.quote_id = q.entity_id', ['sku', 'name', 'created_at', 'updated_at'])
                ->where('q.customer_id = ?', $customerId)
                ->where('qi.parent_item_id IS NULL')
                ->where('qi.created_at >= ?', $searchedAt)
                ->where('(' . implode(' OR ', $conditions) . ')')
                ->order('qi.created_at ASC')
                ->limit(1)
        );

        return is_array($row) ? $row : [];
    }

    private function findOrderMatch(int $customerId, string $searchTerm, string $searchedAt): array
    {
        $connection = $this->resource->getConnection();
        $orderTable = $this->resource->getTableName('sales_order');
        $orderItemTable = $this->resource->getTableName('sales_order_item');

        if (!$connection->isTableExists($orderTable) || !$connection->isTableExists($orderItemTable)) {
            return [];
        }

        $conditions = $this->getItemMatchConditions('soi', $searchTerm);

        if (empty($conditions)) {
            return [];
        }

        $row = $connection->fetchRow(
            $connection->select()
                ->from(['so' => $orderTable], ['order_id' => 'entity_id', 'increment_id'])
                ->join(['soi' => $orderItemTable], 'soi.order_id = so.entity_id', ['sku', 'name', 'created_at'])
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

    private function getItemMatchConditions(string $alias, string $searchTerm): array
    {
        $connection = $this->resource->getConnection();
        $cleanTerm = trim((string)preg_replace('/\s+/', ' ', strtolower($searchTerm)));

        if ($cleanTerm === '') {
            return [];
        }

        $like = '%' . $cleanTerm . '%';

        return [
            $connection->quoteInto('LOWER(' . $alias . '.name) LIKE ?', $like),
            $connection->quoteInto('LOWER(' . $alias . '.sku) LIKE ?', $like),
        ];
    }
}
