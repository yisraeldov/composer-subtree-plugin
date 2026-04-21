<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Config;

final class RepositoriesConfigUpdater
{
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
}
