<?php

namespace Scandiweb\SearchLoss\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Scandiweb_SearchLoss::searchloss';

    private PageFactory $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Scandiweb_SearchLoss::searchloss');
        $resultPage->getConfig()->getTitle()->prepend(__('Search Loss Audit'));

        return $resultPage;
    }
}
