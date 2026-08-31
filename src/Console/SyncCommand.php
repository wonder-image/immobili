<?php

namespace Wonder\Plugin\Immobili\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\Plugin\Immobili\Sync\FeedSyncService;

final class SyncCommand extends ImmobiliCommand
{
    protected function configure(): void
    {
        $this
            ->setName('sync')
            ->setDescription('Sincronizza tutti i feed attivi o un singolo feed.')
            ->addOption('feed', null, InputOption::VALUE_REQUIRED, 'ID del feed da sincronizzare');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->bootstrap($output)) {
            return Command::FAILURE;
        }

        $feed = trim((string) ($input->getOption('feed') ?? ''));
        $service = new FeedSyncService();
        $results = $feed !== ''
            ? [$service->syncById($feed)]
            : $service->syncAll();

        $success = $results !== [];
        foreach ($results as $result) {
            if (empty($result['success'])) {
                $success = false;
                break;
            }
        }

        $output->writeln((string) json_encode([
            'success' => $success,
            'response' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));

        return $success ? Command::SUCCESS : Command::FAILURE;
    }
}
