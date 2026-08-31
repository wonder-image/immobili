<?php

namespace Wonder\Plugin\Immobili\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wonder\Plugin\Immobili\Media\ImageProcessor;

final class ImagesCommand extends ImmobiliCommand
{
    protected function configure(): void
    {
        $this
            ->setName('images')
            ->setDescription('Elabora un lotto di immagini ancora da ridimensionare.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Dimensione del lotto, da 1 a 200', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->bootstrap($output)) {
            return Command::FAILURE;
        }

        $result = (new ImageProcessor())->process((int) $input->getOption('limit'));
        $success = ($result['failed'] ?? 0) === 0;

        $output->writeln((string) json_encode([
            'success' => $success,
            'response' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));

        return $success ? Command::SUCCESS : Command::FAILURE;
    }
}
