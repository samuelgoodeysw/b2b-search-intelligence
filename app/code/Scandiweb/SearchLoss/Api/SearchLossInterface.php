<?php

namespace Scandiweb\SearchLoss\Api;

interface SearchLossInterface
{
    /**
     * Get Search Loss dashboard data
     *
     * @return array
     */
    public function getDashboardData();
}