<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Composer\Command\BaseCommand;
use Composer\Console\Application;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Config\SubtreeTargetConfigProvider;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use ComposerSubtreePlugin\Git\SubtreeGitCommandBuilder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SubtreePullCommand extends BaseCommand
{
    public function __construct(
        Composer $composer,
        private readonly GitProcessRunner $gitRunner,
        private readonly ?SubtreeTargetConfigProvider
            $targetConfigProvider = null,
        private readonly ?SubtreeGitCommandBuilder $commandBuilder = null,
    ) {
        parent::__construct('subtree:pull');
        $this->setComposer($composer);
        $this->setApplication(new Application());
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Pull updates for a configured subtree')
            ->setHelp(
                implode("\n", [
                    'Pull updates for one configured subtree or all configured subtrees.',
                    '',
                    'Examples:',
                    '  composer subtree:pull',
                    '  composer subtree:pull all',
                    '  composer subtree:pull packages/pcre',
                ]),
            )
            ->addArgument(
                'target',
                InputArgument::OPTIONAL,
                'Subtree path target or all',
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $targetConfigs = $this->resolveTargetConfigs($input);

        foreach ($targetConfigs as $subtreeConfig) {
            $this->gitRunner->runOrFail(
                $this->commandBuilder()->fetch($subtreeConfig),
            );
            $this->gitRunner->runOrFail(
                $this->commandBuilder()->pull($subtreeConfig),
            );

            $output->writeln(
                sprintf(
                    'Successfully pulled subtree %s',
                    $subtreeConfig->prefix(),
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
        if (
            $this->targetConfigProvider
            instanceof SubtreeTargetConfigProvider
        ) {
            return $this->targetConfigProvider;
        }

        return new SubtreeTargetConfigProvider();
    }

    private function commandBuilder(): SubtreeGitCommandBuilder
    {
        if ($this->commandBuilder instanceof SubtreeGitCommandBuilder) {
            return $this->commandBuilder;
        }

        return new SubtreeGitCommandBuilder();
    }
}
