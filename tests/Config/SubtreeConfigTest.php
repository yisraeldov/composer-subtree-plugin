<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Config;

use ComposerSubtreePlugin\Config\SubtreeConfig;
use PHPUnit\Framework\TestCase;

final class SubtreeConfigTest extends TestCase
{
    public function testItDefaultsSquashToFalse(): void
    {
        $config = new SubtreeConfig(
            name: 'log',
            prefix: 'packages/log',
            remote: 'https://github.com/php-fig/log.git',
            branch: 'master',
        );

        self::assertFalse($config->squash());
    }

    public function testItExposesRuntimeFields(): void
    {
        $config = new SubtreeConfig(
            name: 'pcre',
            prefix: 'packages/pcre',
            remote: 'https://github.com/composer/pcre.git',
            branch: 'main',
            squash: true,
        );

        self::assertSame('pcre', $config->name());
        self::assertSame('packages/pcre', $config->prefix());
        self::assertSame(
            'https://github.com/composer/pcre.git',
            $config->remote(),
        );
        self::assertSame('main', $config->branch());
        self::assertTrue($config->squash());
    }
}
