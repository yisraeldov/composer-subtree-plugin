<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Composer\Command\BaseCommand;
use Composer\Console\Application;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SubtreePullCommand extends BaseCommand
{
    public function __construct(
        Composer $composer,
        private readonly GitProcessRunner $gitRunner,
    ) {
        parent::__construct('subtree:pull');
        $this->setComposer($composer);
        $this->setApplication(new Application());
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Pull updates for a configured subtree')
            ->addArgument(
                'target',
                InputArgument::OPTIONAL,
                'Subtree name or all',
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        return self::SUCCESS;
    }
}
