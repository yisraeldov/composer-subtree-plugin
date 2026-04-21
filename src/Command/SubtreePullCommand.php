<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Composer\Command\BaseCommand;
use Composer\Console\Application;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Config\SubtreeConfigLoader;
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
        $subtreeConfig = $this->resolveSubtreeConfig(
            $this->readTargetArgument($input),
        );

        $this->gitRunner->runOrFail($this->buildFetchCommand($subtreeConfig));
        $this->gitRunner->runOrFail($this->buildPullCommand($subtreeConfig));

        $output->writeln(
            sprintf('Successfully pulled subtree %s', $subtreeConfig->name()),
        );

        return self::SUCCESS;
    }

    private function readTargetArgument(InputInterface $input): string
    {
        $target = $input->getArgument('target');

        if (!is_string($target) || $target === '' || $target === 'all') {
            throw new \InvalidArgumentException(
                'Provide a configured subtree name as target.',
            );
        }

        return $target;
    }

    private function resolveSubtreeConfig(string $target): SubtreeConfig
    {
        $loader = new SubtreeConfigLoader();
        $configs = $loader->load($this->requireComposer()->getPackage());
        $config = $configs[$target] ?? null;

        if (!$config instanceof SubtreeConfig) {
            throw new \InvalidArgumentException(
                sprintf('Unknown subtree target: %s', $target),
            );
        }

        return $config;
    }

    private function buildFetchCommand(SubtreeConfig $subtreeConfig): string
    {
        return sprintf(
            'git fetch %s %s',
            $subtreeConfig->remote(),
            $subtreeConfig->branch(),
        );
    }

    private function buildPullCommand(SubtreeConfig $subtreeConfig): string
    {
        $command = sprintf(
            'git subtree pull --prefix=%s %s %s',
            $subtreeConfig->prefix(),
            $subtreeConfig->remote(),
            $subtreeConfig->branch(),
        );

        if (!$subtreeConfig->squash()) {
            return $command;
        }

        return $command . ' --squash';
    }
}
