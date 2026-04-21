<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Composer\Command\BaseCommand;
use Composer\Console\Application;
use Composer\Factory;
use Composer\Json\JsonFile;
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
        $this->assertSupportedSubtreeKey($subtreeConfig->name());
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

    private function assertSupportedSubtreeKey(string $key): void
    {
        if (str_contains($key, '.')) {
            throw new \InvalidArgumentException(
                'Subtree key cannot contain dots. Use a key without ".".',
            );
        }
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

        $extra = $this->normalizeMap($manifest['extra'] ?? []);
        $subtrees = $this->normalizeMap($extra['subtrees'] ?? []);

        $subtrees[$config->name()] = [
            'package' => $config->package(),
            'prefix' => $config->prefix(),
            'remote' => $config->remote(),
            'branch' => $config->branch(),
            'squash' => $config->squash(),
        ];

        $extra['subtrees'] = $subtrees;
        $manifest['extra'] = $extra;

        $jsonFile->write($manifest);
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

    /**
     * @param mixed $value
     *
     * @return array<string, mixed>
     */
    private function normalizeMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $entry) {
            if (!is_string($key)) {
                continue;
            }

            $map[$key] = $entry;
        }

        return $map;
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
