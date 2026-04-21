<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Composer\Command\BaseCommand;
use Composer\Console\Application;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Config\SubtreeTargetConfigProvider;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SubtreePushCommand extends BaseCommand
{
    public function __construct(
        Composer $composer,
        private readonly GitProcessRunner $gitRunner,
        private readonly ?SubtreeTargetConfigProvider $targetConfigProvider = null,
    ) {
        parent::__construct('subtree:push');
        $this->setComposer($composer);
        $this->setApplication(new Application());
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Push updates for a configured subtree')
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
                $this->buildPushCommand($subtreeConfig),
            );

            $output->writeln(
                sprintf(
                    'Successfully pushed subtree %s',
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
        return $this->targetConfigProvider()->resolve(
            $this->requireComposer()->getPackage(),
            $input->getArgument('target'),
        );
    }

    private function targetConfigProvider(): SubtreeTargetConfigProvider
    {
        if ($this->targetConfigProvider instanceof SubtreeTargetConfigProvider) {
            return $this->targetConfigProvider;
        }

        return new SubtreeTargetConfigProvider();
    }

    private function buildPushCommand(SubtreeConfig $subtreeConfig): string
    {
        return sprintf(
            'git subtree push --prefix=%s %s %s',
            escapeshellarg($subtreeConfig->prefix()),
            escapeshellarg($subtreeConfig->remote()),
            escapeshellarg($subtreeConfig->branch()),
        );
    }
}
