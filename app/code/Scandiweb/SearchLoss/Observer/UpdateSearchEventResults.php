<?php

namespace Scandiweb\SearchLoss\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class UpdateSearchEventResults implements ObserverInterface
{
    private const SEARCH_EVENT_TABLE = 'scandiweb_searchloss_search_event';
    private const REQUEST_EVENT_ID = '_searchloss_event_id';
    private const REQUEST_STARTED_AT = '_searchloss_started_at_microtime';
    private const REQUEST_TOKEN = '_searchloss_request_token';

    public function __construct(
        private ResourceConnection $resource,
        private StoreManagerInterface $storeManager,
        private LoggerInterface $logger
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            $request = $this->getRequest($observer);

            if (!$request) {
                return;
            }

            $searchTerm = $this->normaliseSearchTerm((string)$request->getParam('q', ''));

            if ($searchTerm === '') {
                return;
            }

            $eventId = (int)$request->getParam(self::REQUEST_EVENT_ID, 0);
            $startedAt = (float)$request->getParam(self::REQUEST_STARTED_AT, 0);
            $requestToken = (string)$request->getParam(self::REQUEST_TOKEN, '');

            $storeId = (int)$this->storeManager->getStore()->getId();
            $resultsCount = $this->getLatestResultCount($searchTerm, $storeId);
            $responseTimeMs = $startedAt > 0 ? (int)round((microtime(true) - $startedAt) * 1000) : null;

            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName(self::SEARCH_EVENT_TABLE);

            $bind = [
                'completed_at' => new \Zend_Db_Expr('CURRENT_TIMESTAMP'),
                'response_time_ms' => $responseTimeMs,
                'results_count' => $resultsCount,
                'completion_status' => 'server_completed',
            ];

            if ($eventId > 0) {
                $updated = $connection->update(
                    $table,
                    $bind,
                    ['event_id = ?' => $eventId]
                );
            } elseif ($requestToken !== '') {
                $updated = $connection->update(
                    $table,
                    $bind,
                    ['request_token = ?' => $requestToken]
                );
            } else {
                $updated = $connection->update(
                    $table,
                    $bind,
                    [
                        'search_term = ?' => $searchTerm,
                        'store_id = ?' => $storeId,
                        'completion_status = ?' => 'started',
                        'searched_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)',
                    ]
                );
            }

            if ($updated > 0) {
                $this->logger->info(
                    'Search Loss logged search completion for ' . $searchTerm . ' in ' . (string)$responseTimeMs . 'ms'
                );
            } else {
                $this->logger->info(
                    'Search Loss did not find a matching started search event to complete: ' . $searchTerm
                );
            }
        } catch (\Throwable $exception) {
            $this->logger->warning(
                'Search Loss could not update logged-in search event result count: ' . $exception->getMessage()
            );
        }
    }

    private function getRequest(Observer $observer)
    {
        $event = $observer->getEvent();
        $request = $event->getRequest();

        if (!$request && $event->getControllerAction()) {
            $request = $event->getControllerAction()->getRequest();
        }

        return $request;
    }

    private function normaliseSearchTerm(string $searchTerm): string
    {
        $searchTerm = preg_replace('/\s+/', ' ', trim($searchTerm));

        if ($searchTerm === null) {
            return '';
        }

        return mb_substr($searchTerm, 0, 255);
    }

    private function getLatestResultCount(string $searchTerm, int $storeId): ?int
    {
        $connection = $this->resource->getConnection();

        $value = $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('search_query'), ['num_results'])
                ->where('query_text = ?', $searchTerm)
                ->where('store_id = ?', $storeId)
                ->order('updated_at DESC')
                ->limit(1)
        );

        if ($value === false || $value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }
}
