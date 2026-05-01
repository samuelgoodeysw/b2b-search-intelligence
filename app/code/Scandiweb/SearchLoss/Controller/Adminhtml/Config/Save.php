<?php

namespace Scandiweb\SearchLoss\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Scandiweb_SearchLoss::searchloss';

    private const XML_PATH_IDENTITY_ATTRIBUTES = 'searchloss/audit/identity_attributes';
    private const XML_PATH_IGNORED_TERMS = 'searchloss/audit/ignored_terms';
    private const XML_PATH_MINIMUM_POPULARITY = 'searchloss/audit/minimum_popularity';
    private const XML_PATH_LOW_ENGAGEMENT_MINIMUM_SEARCHES = 'searchloss/audit/low_engagement_minimum_searches';
    private const XML_PATH_LOW_PRODUCT_ENGAGEMENT_THRESHOLD = 'searchloss/audit/low_product_engagement_threshold';
    private const XML_PATH_LOW_ADD_TO_CART_THRESHOLD = 'searchloss/audit/low_add_to_cart_threshold';
    private const XML_PATH_LOW_PURCHASE_THRESHOLD = 'searchloss/audit/low_purchase_threshold';
    private const XML_PATH_HEALTHY_PURCHASE_THRESHOLD = 'searchloss/audit/healthy_purchase_threshold';

    private WriterInterface $configWriter;
    private TypeListInterface $cacheTypeList;
    private ReinitableConfigInterface $reinitableConfig;

    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->reinitableConfig = $reinitableConfig;
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $identityAttributes = $this->normalizeAttributeCsv((string)$this->getRequest()->getParam('identity_attributes', ''));
            $ignoredTerms = $this->normalizeIgnoredTermsCsv((string)$this->getRequest()->getParam('ignored_terms', ''));
            $minimumPopularity = max(1, (int)$this->getRequest()->getParam('minimum_popularity', 1));
            $lowEngagementMinimumSearches = max(1, (int)$this->getRequest()->getParam('low_engagement_minimum_searches', 5));
            $lowProductEngagementThreshold = $this->normalizePercentage((string)$this->getRequest()->getParam('low_product_engagement_threshold', '20'));
            $lowAddToCartThreshold = $this->normalizePercentage((string)$this->getRequest()->getParam('low_add_to_cart_threshold', '10'));
            $lowPurchaseThreshold = $this->normalizePercentage((string)$this->getRequest()->getParam('low_purchase_threshold', '3'));
            $healthyPurchaseThreshold = $this->normalizePercentage((string)$this->getRequest()->getParam('healthy_purchase_threshold', '5'));

            if ($identityAttributes === '') {
                throw new LocalizedException(__('Product identifier attributes cannot be empty.'));
            }

            $this->configWriter->save(self::XML_PATH_IDENTITY_ATTRIBUTES, $identityAttributes);
            $this->configWriter->save(self::XML_PATH_IGNORED_TERMS, $ignoredTerms);
            $this->configWriter->save(self::XML_PATH_MINIMUM_POPULARITY, (string)$minimumPopularity);
            $this->configWriter->save(self::XML_PATH_LOW_ENGAGEMENT_MINIMUM_SEARCHES, (string)$lowEngagementMinimumSearches);
            $this->configWriter->save(self::XML_PATH_LOW_PRODUCT_ENGAGEMENT_THRESHOLD, (string)$lowProductEngagementThreshold);
            $this->configWriter->save(self::XML_PATH_LOW_ADD_TO_CART_THRESHOLD, (string)$lowAddToCartThreshold);
            $this->configWriter->save(self::XML_PATH_LOW_PURCHASE_THRESHOLD, (string)$lowPurchaseThreshold);
            $this->configWriter->save(self::XML_PATH_HEALTHY_PURCHASE_THRESHOLD, (string)$healthyPurchaseThreshold);

            $this->cacheTypeList->cleanType('config');
            $this->cacheTypeList->cleanType('block_html');
            $this->reinitableConfig->reinit();

            $this->messageManager->addSuccessMessage(
                __('Search Loss Audit configuration saved. The audit now uses the updated settings.')
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('Search Loss Audit configuration could not be saved.'));
        }

        return $resultRedirect->setPath('searchloss/index/index');
    }

    private function normalizePercentage(string $value): string
    {
        $value = trim(str_replace('%', '', $value));

        if ($value === '') {
            $value = '0';
        }

        $number = max(0, min(100, (float)$value));

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function normalizeAttributeCsv(string $value): string
    {
        $items = array_map('trim', explode(',', $value));

        $items = array_map(function ($item) {
            return preg_replace('/[^a-zA-Z0-9_\-]/', '', $item);
        }, $items);

        $items = array_filter($items, function ($item) {
            return $item !== '';
        });

        return implode(',', array_values(array_unique($items)));
    }

    private function normalizeIgnoredTermsCsv(string $value): string
    {
        $items = array_map('trim', explode(',', $value));

        $items = array_map(function ($item) {
            // Preserve normal search phrase characters, including spaces, hyphens, dots, and underscores.
            $item = preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '', $item);
            $item = preg_replace('/\s+/', ' ', trim((string)$item));

            return $item;
        }, $items);

        $items = array_filter($items, function ($item) {
            return $item !== '';
        });

        $uniqueItems = [];
        $seen = [];

        foreach ($items as $item) {
            $key = strtolower($item);

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueItems[] = $item;
            }
        }

        return implode(',', $uniqueItems);
    }
}
