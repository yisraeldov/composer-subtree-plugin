<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Command\SubtreeCommand;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SubtreeAddCommandIntegrationTest extends TestCase
{
    public function testItWritesSubtreeConfigToComposerJson(): void
    {
        $composer = $this->createMock(Composer::class);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getExtra')->willReturn(['subtrees' => []]);
        $composer->method('getPackage')->willReturn($package);

        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::never())->method('runOrFail');

        $command = new SubtreeAddCommand($composer, $gitRunner);
        $tester = new CommandTester($command);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
            'prefix' => 'packages/pcre',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}