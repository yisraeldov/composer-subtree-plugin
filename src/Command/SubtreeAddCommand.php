<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Composer\Command\BaseCommand;
use Composer\Console\Application;
use Composer\Factory;
use Composer\Json\JsonFile;
use ComposerSubtreePlugin\Config\RepositoriesConfigUpdater;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use ComposerSubtreePlugin\Git\SubtreeGitCommandBuilder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SubtreeAddCommand extends BaseCommand
{
    public function __construct(
        Composer $composer,
        private readonly GitProcessRunner $gitRunner,
        private readonly ?string $composerJsonPath = null,
        private readonly ?SubtreeAddInputParser $inputParser = null,
        private readonly ?SubtreeGitCommandBuilder $commandBuilder = null,
    ) {
        parent::__construct('subtree:add');
        $this->setComposer($composer);
        $this->setApplication(new Application());
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Add a new subtree')
            ->setHelp(
                implode("\n", [
                    'Add a subtree and persist repository metadata in composer.json.',
                    '',
                    'Examples:',
                    '  composer subtree:add https://github.com/composer/pcre.git main',
                    '  composer subtree:add https://github.com/composer/pcre.git main packages/pcre --squash',
                ]),
            )
            ->addArgument(
                'upstream-url',
                InputArgument::REQUIRED,
                'Upstream repository URL',
            )
            ->addArgument(
                'upstream-branch',
                InputArgument::REQUIRED,
                'Upstream branch',
            )
            ->addArgument(
                'prefix',
                InputArgument::OPTIONAL,
                'Prefix path for the subtree',
            )
            ->addOption(
                'squash',
                null,
                InputOption::VALUE_NONE,
                'Use squash commit',
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $subtreeConfig = $this->inputParser()->parse($input);
        $gitCommand = $this->buildGitSubtreeAddCommand($subtreeConfig);

        $output->writeln('Running: ' . $gitCommand);

        $this->gitRunner->runOrFail($gitCommand);

        $this->persistSubtreeConfig($subtreeConfig);

        $output->writeln(
            'Successfully added subtree at ' . $subtreeConfig->prefix(),
        );

        return self::SUCCESS;
    }

    private function buildGitSubtreeAddCommand(SubtreeConfig $config): string
    {
        return $this->commandBuilder()->add($config);
    }

    private function persistSubtreeConfig(SubtreeConfig $config): void
    {
        $jsonFile = new JsonFile($this->resolveComposerJsonPath());
        $manifest = $jsonFile->read();

        if (!is_array($manifest)) {
            throw new \RuntimeException(
                'composer.json must decode to an object',
            );
        }

        $manifest['repositories'] = $this->repositoriesConfigUpdater()
            ->upsertSubtreeMetadata(
                $manifest['repositories'] ?? [],
                $config->prefix(),
                $config->remote(),
                $config->branch(),
                $config->squash(),
            );

        $jsonFile->write($manifest);
    }

    private function repositoriesConfigUpdater(): RepositoriesConfigUpdater
    {
        return new RepositoriesConfigUpdater();
    }

    private function resolveComposerJsonPath(): string
    {
        if (
            is_string($this->composerJsonPath)
            && $this->composerJsonPath !== ''
        ) {
            return $this->composerJsonPath;
        }

        return Factory::getComposerFile();
    }

    private function inputParser(): SubtreeAddInputParser
    {
        if ($this->inputParser instanceof SubtreeAddInputParser) {
            return $this->inputParser;
        }

        return new SubtreeAddInputParser();
    }

    private function commandBuilder(): SubtreeGitCommandBuilder
    {
        if ($this->commandBuilder instanceof SubtreeGitCommandBuilder) {
            return $this->commandBuilder;
        }

        return new SubtreeGitCommandBuilder();
    }
}
