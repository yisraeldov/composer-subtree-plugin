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
        $extra = $package->getExtra();
        $subtrees = $extra['subtrees'] ?? null;

        if (!is_array($subtrees)) {
            return [];
        }

        $configs = [];

        foreach ($subtrees as $name => $subtree) {
            if (!is_string($name) || !is_array($subtree)) {
                continue;
            }

            $packageName = $subtree['package'] ?? null;
            $prefix = $subtree['prefix'] ?? null;
            $remote = $subtree['remote'] ?? null;
            $branch = $subtree['branch'] ?? null;

            if (!is_string($packageName) || !is_string($prefix) || !is_string($remote) || !is_string($branch)) {
                continue;
            }

            $squash = $subtree['squash'] ?? false;

            $configs[$name] = new SubtreeConfig(
                name: $name,
                package: $packageName,
                prefix: $prefix,
                remote: $remote,
                branch: $branch,
                squash: is_bool($squash) ? $squash : false,
            );
        }

        return $configs;
    }
}
