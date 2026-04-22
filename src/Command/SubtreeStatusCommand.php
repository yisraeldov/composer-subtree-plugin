<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Composer\Command\BaseCommand;
use Composer\Console\Application;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Config\SubtreeTargetConfigProvider;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SubtreeStatusCommand extends BaseCommand
{
    public function __construct(
        Composer $composer,
        private readonly GitProcessRunner $gitRunner,
        private readonly ?string $projectRoot = null,
        private readonly ?SubtreeTargetConfigProvider $targetConfigProvider = null,
    ) {
        parent::__construct('subtree:status');
        $this->setComposer($composer);
        $this->setApplication(new Application());
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Show configured subtree status details')
            ->setHelp(
                implode("\n", [
                    'Show subtree configuration and operational status.',
                    '',
                    'Examples:',
                    '  composer subtree:status',
                ]),
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $targetConfigs = $this->targetConfigProvider()->resolve(
            $this->requireComposer()->getPackage(),
            'all',
        );

        foreach ($targetConfigs as $name => $config) {
            $output->writeln($this->formatStatusEntry($name, $config));
        }

        return self::SUCCESS;
    }

    private function targetConfigProvider(): SubtreeTargetConfigProvider
    {
        if ($this->targetConfigProvider instanceof SubtreeTargetConfigProvider) {
            return $this->targetConfigProvider;
        }

        return new SubtreeTargetConfigProvider();
    }

    private function formatStatusEntry(string $name, SubtreeConfig $config): string
    {
        return implode("\n", [
            sprintf('name: %s', $name),
            sprintf('package: %s', $this->resolvePackageName($config->prefix())),
            sprintf('prefix: %s', $config->prefix()),
            sprintf('remote: %s', $config->remote()),
            sprintf('branch: %s', $config->branch()),
            sprintf('dirty: %s', $this->resolveDirtyStatus($config->prefix())),
            sprintf(
                'remote-status: %s',
                $this->resolveRemoteStatus($config->remote(), $config->branch()),
            ),
            '',
        ]);
    }

    private function resolvePackageName(string $prefix): string
    {
        $manifestPath = $this->prefixComposerManifestPath($prefix);

        if (!is_file($manifestPath)) {
            return 'unknown';
        }

        $manifestContents = file_get_contents($manifestPath);

        if (!is_string($manifestContents)) {
            return 'unknown';
        }

        $decoded = json_decode($manifestContents, true);

        if (!is_array($decoded)) {
            return 'unknown';
        }

        $name = $decoded['name'] ?? null;

        if (!is_string($name) || $name === '') {
            return 'unknown';
        }

        return $name;
    }

    private function prefixComposerManifestPath(string $prefix): string
    {
        if (str_starts_with($prefix, '/')) {
            return $prefix . '/composer.json';
        }

        return $this->projectRoot() . '/' . $prefix . '/composer.json';
    }

    private function projectRoot(): string
    {
        if (is_string($this->projectRoot) && $this->projectRoot !== '') {
            return $this->projectRoot;
        }

        $workingDirectory = getcwd();

        if (!is_string($workingDirectory)) {
            return '.';
        }

        return $workingDirectory;
    }

    private function resolveDirtyStatus(string $prefix): string
    {
        $command = sprintf(
            'git status --porcelain -- %s',
            escapeshellarg($prefix),
        );
        $result = $this->gitRunner->run($command);

        return trim($result->stdout()) === '' ? 'clean' : 'dirty';
    }

    private function resolveRemoteStatus(string $remote, string $branch): string
    {
        $command = sprintf(
            'git ls-remote --exit-code %s %s',
            escapeshellarg($remote),
            escapeshellarg($branch),
        );
        $result = $this->gitRunner->run($command);

        return $result->isSuccess() ? 'reachable' : 'unreachable';
    }
}
