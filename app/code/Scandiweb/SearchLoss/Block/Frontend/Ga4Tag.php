<?php

namespace Scandiweb\SearchLoss\Block\Frontend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

class Ga4Tag extends Template
{
    private const XML_PATH_FRONTEND_TAG_ENABLED = 'searchloss/ga4/frontend_tag_enabled';
    private const XML_PATH_MEASUREMENT_ID = 'searchloss/ga4/measurement_id';

    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        Context $context,
        array $data = []
    ) {
        $this->scopeConfig = $context->getScopeConfig();
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FRONTEND_TAG_ENABLED,
            ScopeInterface::SCOPE_STORE
        ) && $this->getMeasurementId() !== '';
    }

    public function getMeasurementId(): string
    {
        $measurementId = trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_MEASUREMENT_ID,
            ScopeInterface::SCOPE_STORE
        ));

        if (!preg_match('/^G-[A-Z0-9]+$/i', $measurementId)) {
            return '';
        }

        return $measurementId;
    }
}
