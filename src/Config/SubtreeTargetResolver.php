<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Config;

final class SubtreeTargetResolver
{
    /**
     * @param array<string, SubtreeConfig> $configs
     *
     * @return array<string, SubtreeConfig>
     */
    public function resolve(?string $target, array $configs): array
    {
        if ($this->isAllTarget($target)) {
            ksort($configs);

            return $configs;
        }

        $config = $configs[$target] ?? null;

        if (!$config instanceof SubtreeConfig) {
            throw new \InvalidArgumentException(
                sprintf('Unknown subtree target: %s', $target),
            );
        }

        return [$config->name() => $config];
    }

    private function isAllTarget(?string $target): bool
    {
        return $target === null || $target === '' || $target === 'all';
    }
}
