<?php

namespace Wonder\Plugin\Immobili\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ImmobiliCommand extends Command
{
    public function __construct(private readonly string $root)
    {
        parent::__construct();
    }

    protected function bootstrap(OutputInterface $output): bool
    {
        $bootstrap = rtrim($this->root, '/').'/vendor/wonder-image/app/wonder-image.php';

        if (!is_file($bootstrap)) {
            $output->writeln('<error>Bootstrap wonder-image non trovato nella radice del sito.</error>');
            return false;
        }

        $GLOBALS['ROOT'] = $this->root;
        require_once $bootstrap;

        return true;
    }
}
