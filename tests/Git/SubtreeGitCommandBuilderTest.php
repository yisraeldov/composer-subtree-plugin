<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Git;

use ComposerSubtreePlugin\Config\SubtreeConfig;
use ComposerSubtreePlugin\Git\SubtreeGitCommandBuilder;
use PHPUnit\Framework\TestCase;

final class SubtreeGitCommandBuilderTest extends TestCase
{
    public function testItBuildsEscapedFetchCommand(): void
    {
        $config = new SubtreeConfig(
            'composer/pcre',
            'composer/pcre',
            'packages/pcre',
            'https://github.com/composer/pcre.git',
            'main;echo hacked',
        );

        $builder = new SubtreeGitCommandBuilder();

        self::assertSame(
            "git fetch 'https://github.com/composer/pcre.git' "
            . "'main;echo hacked'",
            $builder->fetch($config),
        );
    }

    public function testItBuildsEscapedPullCommandWithOptionalSquash(): void
    {
        $config = new SubtreeConfig(
            'psr/log',
            'psr/log',
            'packages/log;echo hacked',
            'https://github.com/php-fig/log.git',
            'master',
            true,
        );

        $builder = new SubtreeGitCommandBuilder();

        self::assertSame(
            "git subtree pull --prefix='packages/log;echo hacked' "
            . "'https://github.com/php-fig/log.git' 'master' --squash",
            $builder->pull($config),
        );
    }

    public function testItBuildsEscapedPushCommand(): void
    {
        $config = new SubtreeConfig(
            'zeta/subtree',
            'zeta/subtree',
            'packages/zeta',
            'ssh:example/zeta',
            'main',
        );

        $builder = new SubtreeGitCommandBuilder();

        self::assertSame(
            "git subtree push --prefix='packages/zeta' "
            . "'ssh:example/zeta' 'main'",
            $builder->push($config),
        );
    }
}
