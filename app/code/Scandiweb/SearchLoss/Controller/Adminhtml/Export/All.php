<?php

namespace Scandiweb\SearchLoss\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Scandiweb\SearchLoss\Model\SearchLossDataProvider;

class All extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Backend::admin';

    private FileFactory $fileFactory;
    private SearchLossDataProvider $dataProvider;

    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        SearchLossDataProvider $dataProvider
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->dataProvider = $dataProvider;
    }

    public function execute()
    {
        $period = (string)($this->getRequest()->getParam('period') ?: 'all');
        $safePeriod = preg_replace('/[^a-z0-9_-]/i', '', $period) ?: 'all';

        $rows = $this->dataProvider->getAllFailedSearchReportTerms($period);

        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, [
            'Term',
            'Searches',
            'Est. Demand Value',
            'Priority',
            'Issue Type',
            'Fix Effort',
            'Suggested Action',
            'Full Recommendation',
            'Magento Fix Steps',
            'Last Searched',
        ]);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['term'] ?? '',
                $row['count'] ?? '',
                $row['estimatedDemandValue'] ?? '',
                $row['priority'] ?? '',
                $row['issueType'] ?? '',
                $row['fixEffortBucket'] ?? '',
                $row['suggestedAction'] ?? '',
                $row['fullRecommendation'] ?? '',
                $row['magentoFixSteps'] ?? '',
                $row['lastSearched'] ?? '',
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $this->fileFactory->create(
            'search-loss-all-' . $safePeriod . '.csv',
            $content,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
