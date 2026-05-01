<?php

namespace Scandiweb\SearchLoss\Observer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class LogSearchEvent implements ObserverInterface
{
    private const SEARCH_EVENT_TABLE = 'scandiweb_searchloss_search_event';
    private const REQUEST_EVENT_ID = '_searchloss_event_id';
    private const REQUEST_STARTED_AT = '_searchloss_started_at_microtime';
    private const REQUEST_TOKEN = '_searchloss_request_token';

    public function __construct(
        private CustomerSession $customerSession,
        private ResourceConnection $resource,
        private StoreManagerInterface $storeManager,
        private LoggerInterface $logger
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            $request = $this->getRequest($observer);

            if (!$request) {
                $this->logger->info('Search Loss skipped search event because request was unavailable.');
                return;
            }

            $searchTerm = $this->normaliseSearchTerm((string)$request->getParam('q', ''));

            if ($searchTerm === '') {
                $this->logger->info('Search Loss skipped search event because q parameter was empty.');
                return;
            }

            if (!$this->customerSession->isLoggedIn()) {
                $this->logger->info(
                    'Search Loss skipped search event because customer is not logged in: ' . $searchTerm
                );
                return;
            }

            $customerId = (int)$this->customerSession->getCustomerId();

            if ($customerId <= 0) {
                $this->logger->info(
                    'Search Loss skipped search event because customer ID was unavailable: ' . $searchTerm
                );
                return;
            }

            $storeId = (int)$this->storeManager->getStore()->getId();
            $startedAt = microtime(true);
            $requestToken = bin2hex(random_bytes(16));

            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName(self::SEARCH_EVENT_TABLE);

            $connection->insert(
                $table,
                [
                    'searched_at' => new \Zend_Db_Expr('CURRENT_TIMESTAMP'),
                    'store_id' => $storeId,
                    'customer_id' => $customerId,
                    'search_term' => $searchTerm,
                    'results_count' => null,
                    'source' => 'catalogsearch_predispatch',
                    'completion_status' => 'started',
                    'request_token' => $requestToken,
                    'created_at' => new \Zend_Db_Expr('CURRENT_TIMESTAMP'),
                ]
            );

            $eventId = (int)$connection->lastInsertId($table);

            $request->setParam(self::REQUEST_EVENT_ID, $eventId);
            $request->setParam(self::REQUEST_STARTED_AT, (string)$startedAt);
            $request->setParam(self::REQUEST_TOKEN, $requestToken);

            $this->logger->info(
                'Search Loss logged search start for customer ' . $customerId . ': ' . $searchTerm
            );
        } catch (\Throwable $exception) {
            $this->logger->warning(
                'Search Loss could not log logged-in search event: ' . $exception->getMessage()
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
}
