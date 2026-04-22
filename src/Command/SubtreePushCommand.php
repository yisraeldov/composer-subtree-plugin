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

final class SubtreePushCommand extends BaseCommand
{
    public function __construct(
        Composer $composer,
        private readonly GitProcessRunner $gitRunner,
        private readonly ?SubtreeTargetConfigProvider
            $targetConfigProvider = null,
        private readonly ?SubtreeGitCommandBuilder $commandBuilder = null,
    ) {
        parent::__construct('subtree:push');
        $this->setComposer($composer);
        $this->setApplication(new Application());
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Push updates for a configured subtree')
            ->setHelp(
                implode("\n", [
                    'Push updates for one configured subtree or all configured subtrees.',
                    '',
                    'Examples:',
                    '  composer subtree:push',
                    '  composer subtree:push all',
                    '  composer subtree:push packages/pcre',
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
                $this->commandBuilder()->push($subtreeConfig),
            );

            $output->writeln(
                sprintf(
                    'Successfully pushed subtree %s',
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
