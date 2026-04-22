<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Config;

use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Config\SubtreeConfigLoader;
use PHPUnit\Framework\TestCase;

final class SubtreeConfigLoaderTest extends TestCase
{
    public function testItReturnsEmptyMapWhenSubtreesAreMissing(): void
    {
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getRepositories')->willReturn([]);

        $loader = new SubtreeConfigLoader();

        self::assertSame([], $loader->load($package));
    }

    public function testItMapsSubtreesFromPathRepositories(): void
    {
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getRepositories')->willReturn([
            [
                'type' => 'path',
                'url' => 'packages/log',
                'composer-subtree-plugin' => [
                    'remote' => 'https://github.com/php-fig/log.git',
                    'branch' => 'master',
                ],
            ],
            [
                'type' => 'path',
                'url' => 'packages/pcre',
                'composer-subtree-plugin' => [
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                    'squash' => true,
                ],
            ],
        ]);

        $loader = new SubtreeConfigLoader();

        $configs = $loader->load($package);

        self::assertArrayHasKey('packages/log', $configs);
        self::assertArrayHasKey('packages/pcre', $configs);
        self::assertContainsOnlyInstancesOf(SubtreeConfig::class, $configs);
        self::assertFalse($configs['packages/log']->squash());
        self::assertTrue($configs['packages/pcre']->squash());
        self::assertSame('packages/pcre', $configs['packages/pcre']->prefix());
    }

    public function testItIgnoresRepositoriesWithoutValidSubtreeMetadata(): void
    {
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getRepositories')->willReturn([
            [
                'type' => 'vcs',
                'url' => 'https://github.com/composer/pcre.git',
                'composer-subtree-plugin' => [
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                ],
            ],
            [
                'type' => 'path',
                'url' => 'packages/missing-remote',
                'composer-subtree-plugin' => [
                    'branch' => 'main',
                ],
            ],
            [
                'type' => 'path',
                'url' => 'packages/missing-branch',
                'composer-subtree-plugin' => [
                    'remote' => 'https://github.com/composer/pcre.git',
                ],
            ],
            [
                'type' => 'path',
                'url' => 'packages/non-bool-squash',
                'composer-subtree-plugin' => [
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                    'squash' => 'yes',
                ],
            ],
        ]);

        $loader = new SubtreeConfigLoader();

        $configs = $loader->load($package);

        self::assertSame(['packages/non-bool-squash'], array_keys($configs));
        self::assertFalse($configs['packages/non-bool-squash']->squash());
    }
}
