<?php

namespace Scandiweb\SearchLoss\Console\Command;

use Scandiweb\SearchLoss\Model\Ga4\Sync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Ga4SyncCommand extends Command
{
    public function __construct(
        private Sync $sync
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('searchloss:ga4:sync')
            ->setDescription('Sync GA4 search funnel data into Search Loss table')
            ->addArgument('start_date', InputArgument::OPTIONAL, 'Start date', '7daysAgo')
            ->addArgument('end_date', InputArgument::OPTIONAL, 'End date', 'today');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $count = $this->sync->execute(
                (string)$input->getArgument('start_date'),
                (string)$input->getArgument('end_date')
            );

            $output->writeln("Synced {$count} GA4 search terms.");

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>GA4 sync failed:</error> ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }
}
