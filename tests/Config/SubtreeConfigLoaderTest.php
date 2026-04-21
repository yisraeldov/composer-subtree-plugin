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
        $package->method('getExtra')->willReturn([]);

        $loader = new SubtreeConfigLoader();

        self::assertSame([], $loader->load($package));
    }

    public function testItMapsSubtreesFromComposerExtra(): void
    {
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getExtra')->willReturn([
            'subtrees' => [
                'log' => [
                    'package' => 'psr/log',
                    'prefix' => 'packages/log',
                    'remote' => 'https://github.com/php-fig/log.git',
                    'branch' => 'master',
                ],
                'pcre' => [
                    'package' => 'composer/pcre',
                    'prefix' => 'packages/pcre',
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                    'squash' => true,
                ],
            ],
        ]);

        $loader = new SubtreeConfigLoader();

        $configs = $loader->load($package);

        self::assertArrayHasKey('log', $configs);
        self::assertArrayHasKey('pcre', $configs);
        self::assertContainsOnlyInstancesOf(SubtreeConfig::class, $configs);
        self::assertFalse($configs['log']->squash());
        self::assertTrue($configs['pcre']->squash());
        self::assertSame('composer/pcre', $configs['pcre']->package());
    }
}
