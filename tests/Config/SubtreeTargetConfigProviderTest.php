<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Config;

use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Config\SubtreeTargetConfigProvider;
use PHPUnit\Framework\TestCase;

final class SubtreeTargetConfigProviderTest extends TestCase
{
    public function testItResolvesNamedTargetFromPathRepositoryConfig(): void
    {
        $package = $this->createMock(RootPackageInterface::class);
        $expectedConfig = new SubtreeConfig(
            name: 'packages/pcre',
            prefix: 'packages/pcre',
            remote: 'https://github.com/composer/pcre.git',
            branch: 'main',
        );
        $package->method('getRepositories')->willReturn([
            [
                'type' => 'path',
                'url' => 'packages/pcre',
                'composer-subtree-plugin' => [
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                    'squash' => false,
                ],
            ],
        ]);

        $provider = new SubtreeTargetConfigProvider();

        self::assertEquals(
            ['packages/pcre' => $expectedConfig],
            $provider->resolve($package, 'packages/pcre'),
        );
    }

    public function testItResolvesAllTargetsWhenTargetIsNull(): void
    {
        $package = $this->createMock(RootPackageInterface::class);
        $alphaConfig = new SubtreeConfig(
            name: 'packages/alpha',
            prefix: 'packages/alpha',
            remote: 'https://example.com/alpha.git',
            branch: 'main',
        );
        $zetaConfig = new SubtreeConfig(
            name: 'packages/zeta',
            prefix: 'packages/zeta',
            remote: 'https://example.com/zeta.git',
            branch: 'main',
        );
        $package->method('getRepositories')->willReturn([
            [
                'type' => 'path',
                'url' => 'packages/zeta',
                'composer-subtree-plugin' => [
                    'remote' => 'https://example.com/zeta.git',
                    'branch' => 'main',
                ],
            ],
            [
                'type' => 'path',
                'url' => 'packages/alpha',
                'composer-subtree-plugin' => [
                    'remote' => 'https://example.com/alpha.git',
                    'branch' => 'main',
                ],
            ],
        ]);

        $provider = new SubtreeTargetConfigProvider();

        self::assertEquals(
            [
                'packages/alpha' => $alphaConfig,
                'packages/zeta' => $zetaConfig,
            ],
            $provider->resolve($package, null),
        );
    }
}
