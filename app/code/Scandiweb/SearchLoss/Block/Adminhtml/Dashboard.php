<?php

namespace Scandiweb\SearchLoss\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Scandiweb\SearchLoss\Model\SearchLossDataProvider;

class Dashboard extends Template
{
    private SearchLossDataProvider $dataProvider;

    public function __construct(
        Template\Context $context,
        SearchLossDataProvider $dataProvider,
        array $data = []
    ) {
        $this->dataProvider = $dataProvider;
        parent::__construct($context, $data);
    }

    public function getCurrentPeriod(): string
    {
        return (string)($this->getRequest()->getParam('period') ?: 'all');
    }

    public function getAverageOrderValue(): float
    {
        return $this->dataProvider->getAverageOrderValue();
    }

    public function getFailedSearchTerms(): array
    {
        return $this->dataProvider->getFailedSearchTerms($this->getCurrentPeriod());
    }

    public function getWeakSearchTerms(): array
    {
        return $this->dataProvider->getWeakSearchTerms($this->getCurrentPeriod());
    }

    public function getGa4FunnelTerms(): array
    {
        return $this->dataProvider->getGa4FunnelTerms();
    }

    public function getOpportunityInsights(): array
    {
        return $this->dataProvider->getOpportunityInsights();
    }

    public function getSummary(): array
    {
        return $this->dataProvider->getSummary($this->getCurrentPeriod());
    }

    public function getDashboardPayload(): array
    {
        $payload = [];

        foreach ($this->dataProvider->getDashboardData($this->getCurrentPeriod()) as $item) {
            if (isset($item['key'], $item['value'])) {
                $payload[$item['key']] = $item['value'];
            }
        }

        return $payload;
    }

    public function getSearchData(): array
    {
        $payload = $this->getDashboardPayload();

        return $payload['searchData'] ?? [];
    }

    public function getRankedFailedSearchTerms(): array
    {
        $payload = $this->getDashboardPayload();

        return $payload['failedSearchTerms'] ?? [];
    }
    public function getAuditConfigSaveUrl(): string
    {
        return $this->getUrl('searchloss/config/save');
    }

    public function getConfiguredIdentityAttributes(): string
    {
        return $this->dataProvider->getConfiguredIdentityAttributes();
    }

    public function getConfiguredIgnoredTerms(): string
    {
        return $this->dataProvider->getConfiguredIgnoredTerms();
    }

    public function getConfiguredMinimumPopularity(): int
    {
        return $this->dataProvider->getConfiguredMinimumPopularity();
    }

    public function getConfiguredLowEngagementMinimumSearches(): int
    {
        return $this->dataProvider->getConfiguredLowEngagementMinimumSearches();
    }

    public function getConfiguredLowProductEngagementThreshold(): float
    {
        return $this->dataProvider->getConfiguredLowProductEngagementThreshold();
    }

    public function getConfiguredLowAddToCartThreshold(): float
    {
        return $this->dataProvider->getConfiguredLowAddToCartThreshold();
    }

    public function getConfiguredLowPurchaseThreshold(): float
    {
        return $this->dataProvider->getConfiguredLowPurchaseThreshold();
    }

    public function getConfiguredHealthyPurchaseThreshold(): float
    {
        return $this->dataProvider->getConfiguredHealthyPurchaseThreshold();
    }

    public function getLowEngagementSearchTerms(): array
    {
        $payload = $this->getDashboardPayload();

        $terms = $payload['lowEngagementSearchTerms'] ?? [];

        return array_values(array_filter($terms, function ($term) {
            return !empty($term['isLowEngagementFinding']);
        }));
    }

    public function getLoggedInSearchIntelligence(): array
    {
        $payload = $this->getDashboardPayload();

        return $payload['loggedInSearchIntelligence'] ?? [];
    }

}
