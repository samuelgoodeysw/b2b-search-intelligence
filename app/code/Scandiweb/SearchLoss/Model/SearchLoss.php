<?php

namespace Scandiweb\SearchLoss\Model;

use Scandiweb\SearchLoss\Api\SearchLossInterface;

class SearchLoss implements SearchLossInterface
{
    private SearchLossDataProvider $dataProvider;

    public function __construct(SearchLossDataProvider $dataProvider)
    {
        $this->dataProvider = $dataProvider;
    }

    public function getDashboardData()
    {
        return $this->dataProvider->getDashboardData();
    }
}