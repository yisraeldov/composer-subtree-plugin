<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Config;

use Composer\Package\RootPackageInterface;

final class SubtreeConfigLoader
{
    /**
     * @return array<string, SubtreeConfig>
     */
    public function load(RootPackageInterface $package): array
    {
        $repositories = $package->getRepositories();

        $configs = [];

        foreach ($repositories as $repository) {
            $config = $this->toSubtreeConfig($repository);

            if ($config !== null) {
                $configs[$config->name()] = $config;
            }
        }

        return $configs;
    }

    private function toSubtreeConfig(mixed $repository): ?SubtreeConfig
    {
        $pathRepository = $this->asPathRepository($repository);

        if ($pathRepository === null) {
            return null;
        }

        $prefix = $this->extractPrefix($pathRepository);
        $metadata = $this->extractSubtreeMetadata($pathRepository);
        $upstream = $this->extractUpstream($metadata);

        if ($prefix === null || $metadata === null || $upstream === null) {
            return null;
        }

        return new SubtreeConfig(
            name: $prefix,
            prefix: $prefix,
            remote: $upstream['remote'],
            branch: $upstream['branch'],
            squash: $this->parseSquash($metadata),
        );
    }

    /**
     * @param array<mixed> $repository
     */
    private function isPathRepository(array $repository): bool
    {
        return ($repository['type'] ?? null) === 'path';
    }

    /**
     * @return array<mixed>|null
     */
    private function asPathRepository(mixed $repository): ?array
    {
        if (!is_array($repository) || !$this->isPathRepository($repository)) {
            return null;
        }

        return $repository;
    }

    /**
     * @param array<mixed> $repository
     */
    private function extractPrefix(array $repository): ?string
    {
        $prefix = $repository['url'] ?? null;

        return is_string($prefix) ? $prefix : null;
    }

    /**
     * @param array<mixed> $repository
     *
     * @return array<mixed>|null
     */
    private function extractSubtreeMetadata(array $repository): ?array
    {
        $metadata = $repository['composer-subtree-plugin'] ?? null;

        return is_array($metadata) ? $metadata : null;
    }

    /**
     * @param array<mixed>|null $metadata
     *
     * @return array{remote: string, branch: string}|null
     */
    private function extractUpstream(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $remote = $metadata['remote'] ?? null;
        $branch = $metadata['branch'] ?? null;

        if (!is_string($remote) || !is_string($branch)) {
            return null;
        }

        return ['remote' => $remote, 'branch' => $branch];
    }

    /**
     * @param array<mixed> $metadata
     */
    private function parseSquash(array $metadata): bool
    {
        $squash = $metadata['squash'] ?? false;

        return is_bool($squash) ? $squash : false;
    }
}
