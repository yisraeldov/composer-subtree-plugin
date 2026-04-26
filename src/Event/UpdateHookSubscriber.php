<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Event;

use Composer\Package\RootPackageInterface;
use Composer\Plugin\PreCommandRunEvent;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Config\SubtreeConfigLoader;

final class UpdateHookSubscriber
{
    private const UPDATE_COMMAND = 'update';

    public function __construct(
        private readonly ?SubtreeConfigLoader $configLoader = null,
        private readonly ?string $projectRoot = null,
    ) {}

    public function onPreCommandRun(PreCommandRunEvent $event): void
    {
        if (!$this->shouldRunFor($event)) {
            return;
        }
    }

    public function shouldRunFor(PreCommandRunEvent $event): bool
    {
        return $event->getCommand() === self::UPDATE_COMMAND;
    }

    /**
     * @return array<string, SubtreeConfig>
     */
    public function targetedSubtrees(
        PreCommandRunEvent $event,
        RootPackageInterface $package,
    ): array {
        if (!$this->shouldRunFor($event)) {
            return [];
        }

        $requestedPackages = $this->requestedPackages($event);

        if ($requestedPackages === []) {
            return [];
        }

        $requestedPackageLookup = array_fill_keys($requestedPackages, true);

        return array_reduce(
            $this->configLoader()->load($package),
            fn(array $targeted, SubtreeConfig $config): array =>
                $this->appendWhenRequested($targeted, $config, $requestedPackageLookup),
            [],
        );
    }

    /**
     * @return array<int, string>
     */
    private function requestedPackages(PreCommandRunEvent $event): array
    {
        $packages = $event->getInput()->getArgument('packages');

        if (!is_array($packages)) {
            return [];
        }

        return array_values(
            array_filter($packages, static fn(mixed $package): bool => is_string($package) && $package !== ''),
        );
    }

    /**
     * @param array<string, bool> $requestedPackageLookup
     * @param array<string, SubtreeConfig> $targeted
     *
     * @return array<string, SubtreeConfig>
     */
    private function appendWhenRequested(
        array $targeted,
        SubtreeConfig $config,
        array $requestedPackageLookup,
    ): array {
        $packageName = $this->packageNameForPrefix($config->prefix());

        if ($packageName === null || !isset($requestedPackageLookup[$packageName])) {
            return $targeted;
        }

        $targeted[$config->prefix()] = $config;

        return $targeted;
    }

    private function packageNameForPrefix(string $prefix): ?string
    {
        $manifestPath = $this->manifestPathForPrefix($prefix);

        if (!is_file($manifestPath)) {
            return null;
        }

        $manifestContents = file_get_contents($manifestPath);

        if (!is_string($manifestContents)) {
            return null;
        }

        $decoded = json_decode($manifestContents, true);

        if (!is_array($decoded)) {
            return null;
        }

        $name = $decoded['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function manifestPathForPrefix(string $prefix): string
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

        return is_string($workingDirectory) ? $workingDirectory : '.';
    }

    private function configLoader(): SubtreeConfigLoader
    {
        if ($this->configLoader instanceof SubtreeConfigLoader) {
            return $this->configLoader;
        }

        return new SubtreeConfigLoader();
    }
}
