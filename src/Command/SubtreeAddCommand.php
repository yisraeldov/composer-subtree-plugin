<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SubtreeAddCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('subtree:add')
            ->setDescription('Add a new subtree')
            ->addArgument('upstream-url', InputArgument::REQUIRED, 'Upstream repository URL')
            ->addArgument('upstream-branch', InputArgument::REQUIRED, 'Upstream branch')
            ->addArgument('prefix', InputArgument::OPTIONAL, 'Prefix path for the subtree')
            ->addOption('squash', null, InputOption::VALUE_NONE, 'Use squash commit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}
