<?php

namespace Scandiweb\SearchLoss\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Scandiweb\SearchLoss\Model\SearchLossDataProvider;

class Dashboard extends Template
{
    public function __construct(
        Template\Context $context,
        private SearchLossDataProvider $dataProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getLoggedInSearchIntelligence(): array
    {
        return $this->dataProvider->getLoggedInSearchIntelligence();
    }

    public function getSummary(): array
    {
        return $this->dataProvider->getSummary($this->getLoggedInSearchIntelligence());
    }
}
