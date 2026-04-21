<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Config;

use Composer\Package\RootPackageInterface;

final class SubtreeConfigLoader
{
    /** @var list<string> */
    private const REQUIRED_FIELDS = ['package', 'prefix', 'remote', 'branch'];

    /**
     * @return array<string, SubtreeConfig>
     */
    public function load(RootPackageInterface $package): array
    {
        $extra = $package->getExtra();
        $subtrees = $extra['subtrees'] ?? null;

        if (!is_array($subtrees)) {
            return [];
        }

        $configs = [];

        foreach ($subtrees as $name => $subtree) {
            $config = $this->toSubtreeConfig($name, $subtree);

            if ($config !== null) {
                $configs[$config->name()] = $config;
            }
        }

        return $configs;
    }

    private function toSubtreeConfig(
        mixed $name,
        mixed $subtree,
    ): ?SubtreeConfig {
        if (!is_string($name) || !is_array($subtree)) {
            return null;
        }

        $fields = $this->extractRequiredFields($subtree);

        if ($fields === null) {
            return null;
        }

        return new SubtreeConfig(
            name: $name,
            package: $fields['package'],
            prefix: $fields['prefix'],
            remote: $fields['remote'],
            branch: $fields['branch'],
            squash: $this->parseSquash($subtree),
        );
    }

    /**
     * @param array<mixed> $subtree
     *
     * @return array<string, string>|null
     */
    private function extractRequiredFields(array $subtree): ?array
    {
        $fields = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            $value = $subtree[$field] ?? null;

            if (!is_string($value)) {
                return null;
            }

            $fields[$field] = $value;
        }

        return $fields;
    }

    /**
     * @param array<mixed> $subtree
     */
    private function parseSquash(array $subtree): bool
    {
        $squash = $subtree['squash'] ?? false;

        return is_bool($squash) ? $squash : false;
    }
}
