<?php

namespace Scandiweb\SearchLoss\Console\Command;

use Scandiweb\SearchLoss\Model\Ga4\Probe;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Ga4ProbeCommand extends Command
{
    public function __construct(
        private Probe $probe
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('searchloss:ga4:probe')
            ->setDescription('Probe whether GA4 can support Search Loss low-engagement diagnostics')
            ->addArgument('start_date', InputArgument::OPTIONAL, 'Start date', '28daysAgo')
            ->addArgument('end_date', InputArgument::OPTIONAL, 'End date', 'today');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startDate = (string)$input->getArgument('start_date');
        $endDate = (string)$input->getArgument('end_date');

        $output->writeln('');
        $output->writeln('<info>Search Loss GA4 Probe</info>');
        $output->writeln('Date range: ' . $startDate . ' to ' . $endDate);
        $output->writeln('');

        $results = $this->probe->execute($startDate, $endDate);

        foreach ($results as $result) {
            $status = (string)($result['status'] ?? 'INFO');
            $check = (string)($result['check'] ?? 'Check');
            $message = (string)($result['message'] ?? '');

            $tag = match ($status) {
                'PASS' => '<info>PASS</info>',
                'WARN' => '<comment>WARN</comment>',
                'FAIL' => '<error>FAIL</error>',
                default => '<comment>INFO</comment>',
            };

            $output->writeln($tag . ' ' . $check . ' - ' . $message);

            foreach (($result['details'] ?? []) as $key => $value) {
                $output->writeln('     ' . $key . ': ' . $value);
            }
        }

        $hasFail = false;

        foreach ($results as $result) {
            if (($result['status'] ?? '') === 'FAIL') {
                $hasFail = true;
                break;
            }
        }

        $output->writeln('');

        return $hasFail ? Command::FAILURE : Command::SUCCESS;
    }
}
