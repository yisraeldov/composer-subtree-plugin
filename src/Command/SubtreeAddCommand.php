<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SubtreeAddCommand extends Command
{
    private const PACKAGES_PREFIX = 'packages/';

    public function __construct(
        private readonly Composer $composer,
    ) {
        parent::__construct('subtree:add');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Add a new subtree')
            ->addArgument('upstream-url', InputArgument::REQUIRED, 'Upstream repository URL')
            ->addArgument('upstream-branch', InputArgument::REQUIRED, 'Upstream branch')
            ->addArgument('prefix', InputArgument::OPTIONAL, 'Prefix path for the subtree')
            ->addOption('squash', null, InputOption::VALUE_NONE, 'Use squash commit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $upstreamUrl = $input->getArgument('upstream-url');
        $upstreamBranch = $input->getArgument('upstream-branch');
        $prefix = $input->getArgument('prefix');
        $squash = $input->getOption('squash');

        if ($prefix === null) {
            $prefix = $this->defaultPrefixFromUrl($upstreamUrl);
        }

        $output->writeln('Would run: git subtree add --prefix=' . $prefix . ' ' . $upstreamUrl . ' ' . $upstreamBranch . ($squash ? ' --squash' : ' --no-squash'));

        return Command::SUCCESS;
    }

    private function defaultPrefixFromUrl(string $url): string
    {
        $basename = basename($url, '.git');

        return self::PACKAGES_PREFIX . $basename;
    }
}
