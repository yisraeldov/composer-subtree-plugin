<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Composer\Command\BaseCommand;
use Composer\Console\Application;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Config\SubtreeConfigLoader;
use ComposerSubtreePlugin\Config\SubtreeTargetResolver;
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
        $targetConfigs = $this->resolveTargetConfigs($input);

        foreach ($targetConfigs as $subtreeConfig) {
            $this->gitRunner->runOrFail(
                $this->buildFetchCommand($subtreeConfig),
            );
            $this->gitRunner->runOrFail(
                $this->buildPullCommand($subtreeConfig),
            );

            $output->writeln(
                sprintf(
                    'Successfully pulled subtree %s',
                    $subtreeConfig->name(),
                ),
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, SubtreeConfig>
     */
    private function resolveTargetConfigs(InputInterface $input): array
    {
        $target = $input->getArgument('target');
        if (!is_string($target)) {
            $target = null;
        }

        $loader = new SubtreeConfigLoader();
        $configs = $loader->load($this->requireComposer()->getPackage());

        return (new SubtreeTargetResolver())->resolve($target, $configs);
    }

    private function buildFetchCommand(SubtreeConfig $subtreeConfig): string
    {
        return sprintf(
            'git fetch %s %s',
            escapeshellarg($subtreeConfig->remote()),
            escapeshellarg($subtreeConfig->branch()),
        );
    }

    private function buildPullCommand(SubtreeConfig $subtreeConfig): string
    {
        $command = sprintf(
            'git subtree pull --prefix=%s %s %s',
            escapeshellarg($subtreeConfig->prefix()),
            escapeshellarg($subtreeConfig->remote()),
            escapeshellarg($subtreeConfig->branch()),
        );

        if (!$subtreeConfig->squash()) {
            return $command;
        }

        return $command . ' --squash';
    }
}
