<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Config;

use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Config\SubtreeTargetConfigProvider;
use PHPUnit\Framework\TestCase;

final class SubtreeTargetConfigProviderTest extends TestCase
{
    public function testItResolvesNamedTargetFromPackageConfig(): void
    {
        $package = $this->createMock(RootPackageInterface::class);
        $expectedConfig = new SubtreeConfig(
            'composer/pcre',
            'composer/pcre',
            'packages/pcre',
            'https://github.com/composer/pcre.git',
            'main',
        );
        $package->method('getExtra')->willReturn([
            'subtrees' => [
                'composer/pcre' => [
                    'package' => 'composer/pcre',
                    'prefix' => 'packages/pcre',
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                    'squash' => false,
                ],
            ],
        ]);

        $provider = new SubtreeTargetConfigProvider();

        self::assertEquals(
            ['composer/pcre' => $expectedConfig],
            $provider->resolve($package, 'composer/pcre'),
        );
    }

    public function testItResolvesAllTargetsWhenTargetIsNull(): void
    {
        $package = $this->createMock(RootPackageInterface::class);
        $alphaConfig = new SubtreeConfig(
            'alpha/subtree',
            'alpha/subtree',
            'packages/alpha',
            'https://example.com/alpha.git',
            'main',
        );
        $zetaConfig = new SubtreeConfig(
            'zeta/subtree',
            'zeta/subtree',
            'packages/zeta',
            'https://example.com/zeta.git',
            'main',
        );
        $package->method('getExtra')->willReturn([
            'subtrees' => [
                'zeta/subtree' => [
                    'package' => 'zeta/subtree',
                    'prefix' => 'packages/zeta',
                    'remote' => 'https://example.com/zeta.git',
                    'branch' => 'main',
                ],
                'alpha/subtree' => [
                    'package' => 'alpha/subtree',
                    'prefix' => 'packages/alpha',
                    'remote' => 'https://example.com/alpha.git',
                    'branch' => 'main',
                ],
            ],
        ]);

        $provider = new SubtreeTargetConfigProvider();

        self::assertEquals(
            [
                'alpha/subtree' => $alphaConfig,
                'zeta/subtree' => $zetaConfig,
            ],
            $provider->resolve($package, null),
        );
    }
}
