<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Config;

final class RepositoriesConfigUpdater
{
    private const SUBTREE_METADATA_KEY = 'composer-subtree-plugin';

    /**
     * @param mixed $repositories
     *
     * @return array<mixed>
     */
    public function ensurePathRepository(
        mixed $repositories,
        string $prefix,
    ): array {
        if (!is_array($repositories)) {
            return [['type' => 'path', 'url' => $prefix]];
        }

        if ($this->hasPathRepositoryForPrefix($repositories, $prefix)) {
            return $repositories;
        }

        $repositories[] = ['type' => 'path', 'url' => $prefix];

        return $repositories;
    }

    /**
     * @param mixed $repositories
     *
     * @return array<mixed>
     */
    public function upsertSubtreeMetadata(
        mixed $repositories,
        string $prefix,
        string $remote,
        string $branch,
        bool $squash,
    ): array {
        $metadata = $this->buildSubtreeMetadata($remote, $branch, $squash);

        $updatedRepositories = $this->updatePathRepositories(
            $this->normalizeRepositories($repositories, $prefix, $metadata),
            $prefix,
            $metadata,
        );

        if ($this->hasPathRepositoryForPrefix($updatedRepositories, $prefix)) {
            return $updatedRepositories;
        }

        $updatedRepositories[] = $this->createPathRepository(
            $prefix,
            $metadata,
        );

        return $updatedRepositories;
    }

    /**
     * @param array{remote: string, branch: string, squash: bool} $metadata
     *
     * @return array<mixed>
     */
    private function normalizeRepositories(
        mixed $repositories,
        string $prefix,
        array $metadata,
    ): array {
        if (is_array($repositories)) {
            return $repositories;
        }

        return [$this->createPathRepository($prefix, $metadata)];
    }

    /**
     * @return array{remote: string, branch: string, squash: bool}
     */
    private function buildSubtreeMetadata(
        string $remote,
        string $branch,
        bool $squash,
    ): array {
        return [
            'remote' => $remote,
            'branch' => $branch,
            'squash' => $squash,
        ];
    }

    /**
     * @param array{remote: string, branch: string, squash: bool} $metadata
     *
     * @return array<string, mixed>
     */
    private function createPathRepository(
        string $prefix,
        array $metadata,
    ): array {
        return [
            'type' => 'path',
            'url' => $prefix,
            self::SUBTREE_METADATA_KEY => $metadata,
        ];
    }

    /**
     * @param array<mixed> $repositories
     * @param array{remote: string, branch: string, squash: bool} $metadata
     *
     * @return array<mixed>
     */
    private function updatePathRepositories(
        array $repositories,
        string $prefix,
        array $metadata,
    ): array {
        return array_map(
            fn(mixed $repository): mixed => $this->updateMatchingPathRepository(
                $repository,
                $prefix,
                $metadata,
            ),
            $repositories,
        );
    }

    /**
     * @param array<mixed> $repositories
     */
    private function hasPathRepositoryForPrefix(
        array $repositories,
        string $prefix,
    ): bool {
        return array_reduce(
            $repositories,
            static fn(bool $hasRepository, mixed $repository): bool
                => $hasRepository
                    || (
                        is_array($repository)
                        && ($repository['type'] ?? null) === 'path'
                        && ($repository['url'] ?? null) === $prefix
                    ),
            false,
        );
    }

    /**
     * @param array{remote: string, branch: string, squash: bool} $metadata
     */
    private function updateMatchingPathRepository(
        mixed $repository,
        string $prefix,
        array $metadata,
    ): mixed {
        if (!$this->isMatchingPathRepository($repository, $prefix)) {
            return $repository;
        }

        $repository = $this->normalizeRepository($repository);

        $repository[self::SUBTREE_METADATA_KEY] = $metadata;

        return $repository;
    }

    private function isMatchingPathRepository(
        mixed $repository,
        string $prefix,
    ): bool {
        if (!is_array($repository)) {
            return false;
        }

        return ($repository['type'] ?? null) === 'path'
            && ($repository['url'] ?? null) === $prefix;
    }

    /**
     * @return array<mixed>
     */
    private function normalizeRepository(mixed $repository): array
    {
        return is_array($repository) ? $repository : [];
    }
}
