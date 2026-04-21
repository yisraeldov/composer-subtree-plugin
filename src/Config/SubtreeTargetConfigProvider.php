<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Config;

use Composer\Package\RootPackageInterface;

final class SubtreeTargetConfigProvider
{
    public function __construct(
        private readonly ?SubtreeConfigLoader $loader = null,
        private readonly ?SubtreeTargetResolver $resolver = null,
    ) {}

    /**
     * @return array<string, SubtreeConfig>
     */
    public function resolve(RootPackageInterface $package, mixed $target): array
    {
        $targetName = is_string($target) ? $target : null;
        $configs = $this->loader()->load($package);

        return $this->resolver()->resolve($targetName, $configs);
    }

    private function loader(): SubtreeConfigLoader
    {
        if ($this->loader instanceof SubtreeConfigLoader) {
            return $this->loader;
        }

        return new SubtreeConfigLoader();
    }

    private function resolver(): SubtreeTargetResolver
    {
        if ($this->resolver instanceof SubtreeTargetResolver) {
            return $this->resolver;
        }

        return new SubtreeTargetResolver();
    }
}
