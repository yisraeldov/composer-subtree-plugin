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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SubtreeAddCommand extends BaseCommand
{
    private const PACKAGES_PREFIX = 'packages/';

    public function __construct(
        Composer $composer,
        private readonly GitProcessRunner $gitRunner,
        private readonly ?string $composerJsonPath = null,
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
        $subtreeConfig = $this->buildSubtreeConfigFromInput($input);
        $this->assertSupportedSubtreeKey($subtreeConfig->name());
        $gitCommand = $this->buildGitSubtreeAddCommand($subtreeConfig);

        $this->persistSubtreeConfig($subtreeConfig);

        $output->writeln('Running: ' . $gitCommand);

        $this->gitRunner->runOrFail($gitCommand);

        $output->writeln(
            'Successfully added subtree at ' . $subtreeConfig->prefix(),
        );

        return self::SUCCESS;
    }

    private function buildSubtreeConfigFromInput(
        InputInterface $input,
    ): SubtreeConfig {
        $upstreamUrl = $this->readRequiredStringArgument($input, 'upstream-url');
        $upstreamBranch = $this->readRequiredStringArgument(
            $input,
            'upstream-branch',
        );

        $prefix = $input->getArgument('prefix');

        $packageName = $this->extractPackageName($upstreamUrl);

        return new SubtreeConfig(
            name: $packageName,
            package: $packageName,
            prefix: $this->normalizePrefix($prefix, $upstreamUrl),
            remote: $upstreamUrl,
            branch: $upstreamBranch,
            squash: $this->readBoolOption($input, 'squash'),
        );
    }

    private function readRequiredStringArgument(
        InputInterface $input,
        string $argument,
    ): string {
        $value = $input->getArgument($argument);

        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException(
                sprintf('Argument %s must be a non-empty string', $argument),
            );
        }

        return $value;
    }

    private function normalizePrefix(mixed $prefix, string $upstreamUrl): string
    {
        if ($prefix === null) {
            return $this->defaultPrefixFromUrl($upstreamUrl);
        }

        if (is_string($prefix)) {
            return $prefix;
        }

        return $this->defaultPrefixFromUrl($upstreamUrl);
    }

    private function readBoolOption(InputInterface $input, string $name): bool
    {
        return (bool) $input->getOption($name);
    }

    private function defaultPrefixFromUrl(string $url): string
    {
        $basename = basename($this->extractPackageName($url));

        return self::PACKAGES_PREFIX . $basename;
    }

    private function extractPackageName(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            return $this->trimGitSuffix(trim($path, '/'));
        }

        $colonPosition = strrpos($url, ':');

        if ($colonPosition === false) {
            return $this->trimGitSuffix(basename($url));
        }

        $suffix = substr($url, $colonPosition + 1);

        return $this->trimGitSuffix(trim($suffix, '/'));
    }

    private function trimGitSuffix(string $path): string
    {
        if (str_ends_with($path, '.git')) {
            return substr($path, 0, -4);
        }

        return $path;
    }

    private function buildGitSubtreeAddCommand(SubtreeConfig $config): string
    {
        $command = sprintf(
            'git subtree add --prefix=%s %s %s',
            $config->prefix(),
            $config->remote(),
            $config->branch(),
        );

        if ($config->squash()) {
            return $command . ' --squash';
        }

        return $command;
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
        $jsonFile = new JsonFile($this->composerJsonPath ?? Factory::getComposerFile());
        $manifest = $jsonFile->read();

        if (!is_array($manifest)) {
            throw new \RuntimeException('composer.json must decode to an object');
        }

        $extra = $manifest['extra'] ?? [];

        if (!is_array($extra)) {
            $extra = [];
        }

        $subtrees = $extra['subtrees'] ?? [];

        if (!is_array($subtrees)) {
            $subtrees = [];
        }

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
}
