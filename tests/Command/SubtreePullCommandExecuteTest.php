<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Command\SubtreePullCommand;
use ComposerSubtreePlugin\Git\GitProcessResult;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SubtreePullCommandExecuteTest extends TestCase
{
    public function testItRunsFetchThenSubtreePullForNamedSubtree(): void
    {
        $commands = [];
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::exactly(2))
            ->method('runOrFail')
            ->willReturnCallback(
                function (string $command) use (&$commands): GitProcessResult {
                    $commands[] = $command;

                    return new GitProcessResult(0, '', '');
                },
            );

        $tester = $this->createCommandTester(
            $gitRunner,
            [
                'composer/pcre' => [
                    'package' => 'composer/pcre',
                    'prefix' => 'packages/pcre',
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                    'squash' => false,
                ],
            ],
        );

        $tester->execute(['target' => 'composer/pcre']);

        self::assertSame(
            [
                'git fetch https://github.com/composer/pcre.git main',
                'git subtree pull --prefix=packages/pcre '
                . 'https://github.com/composer/pcre.git main',
            ],
            $commands,
        );
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testItUsesSquashFlagWhenConfigured(): void
    {
        $commands = [];
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::exactly(2))
            ->method('runOrFail')
            ->willReturnCallback(
                function (string $command) use (&$commands): GitProcessResult {
                    $commands[] = $command;

                    return new GitProcessResult(0, '', '');
                },
            );

        $tester = $this->createCommandTester(
            $gitRunner,
            [
                'psr/log' => [
                    'package' => 'psr/log',
                    'prefix' => 'packages/log',
                    'remote' => 'https://github.com/php-fig/log.git',
                    'branch' => 'master',
                    'squash' => true,
                ],
            ],
        );

        $tester->execute(['target' => 'psr/log']);

        self::assertSame(
            [
                'git fetch https://github.com/php-fig/log.git master',
                'git subtree pull --prefix=packages/log '
                . 'https://github.com/php-fig/log.git master --squash',
            ],
            $commands,
        );
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testItPullsAllSubtreesWhenTargetIsOmitted(): void
    {
        $commands = [];
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::exactly(4))
            ->method('runOrFail')
            ->willReturnCallback(
                function (string $command) use (&$commands): GitProcessResult {
                    $commands[] = $command;

                    return new GitProcessResult(0, '', '');
                },
            );

        $tester = $this->createCommandTester(
            $gitRunner,
            [
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
                    'branch' => 'master',
                ],
            ],
        );

        $tester->execute([]);

        self::assertSame(
            [
                'git fetch https://example.com/alpha.git master',
                'git subtree pull --prefix=packages/alpha '
                . 'https://example.com/alpha.git master',
                'git fetch https://example.com/zeta.git main',
                'git subtree pull --prefix=packages/zeta '
                . 'https://example.com/zeta.git main',
            ],
            $commands,
        );
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testItPullsAllSubtreesWhenTargetIsAll(): void
    {
        $commands = [];
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::exactly(4))
            ->method('runOrFail')
            ->willReturnCallback(
                function (string $command) use (&$commands): GitProcessResult {
                    $commands[] = $command;

                    return new GitProcessResult(0, '', '');
                },
            );

        $tester = $this->createCommandTester(
            $gitRunner,
            [
                'b/subtree' => [
                    'package' => 'b/subtree',
                    'prefix' => 'packages/b',
                    'remote' => 'https://example.com/b.git',
                    'branch' => 'main',
                ],
                'a/subtree' => [
                    'package' => 'a/subtree',
                    'prefix' => 'packages/a',
                    'remote' => 'https://example.com/a.git',
                    'branch' => 'main',
                ],
            ],
        );

        $tester->execute(['target' => 'all']);

        self::assertSame(
            [
                'git fetch https://example.com/a.git main',
                'git subtree pull --prefix=packages/a '
                . 'https://example.com/a.git main',
                'git fetch https://example.com/b.git main',
                'git subtree pull --prefix=packages/b '
                . 'https://example.com/b.git main',
            ],
            $commands,
        );
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testItFailsWhenNamedSubtreeIsNotConfigured(): void
    {
        $tester = $this->createCommandTester(
            $this->createMock(GitProcessRunner::class),
            [],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown subtree target: composer/pcre');

        $tester->execute(['target' => 'composer/pcre']);
    }

    /**
     * @param array<string, array<string, mixed>> $subtrees
     */
    private function createCommandTester(
        GitProcessRunner $gitRunner,
        array $subtrees,
    ): CommandTester {
        $composer = $this->createMock(Composer::class);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getExtra')->willReturn(['subtrees' => $subtrees]);
        $composer->method('getPackage')->willReturn($package);

        return new CommandTester(
            new SubtreePullCommand($composer, $gitRunner),
        );
    }
}
