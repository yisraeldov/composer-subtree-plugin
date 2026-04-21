<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Config\SubtreeConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SubtreeAddCommandExecuteTest extends TestCase
{
    public function testItDefaultsPrefixToPackagesRepoName(): void
    {
        $composer = $this->createMock(Composer::class);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getExtra')->willReturn(['subtrees' => []]);
        $composer->method('getPackage')->willReturn($package);

        $command = new SubtreeAddCommand($composer);
        $tester = new CommandTester($command);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('packages/pcre', $output);
    }

    public function testItDefaultsSquashToFalse(): void
    {
        $composer = $this->createMock(Composer::class);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getExtra')->willReturn(['subtrees' => []]);
        $composer->method('getPackage')->willReturn($package);

        $command = new SubtreeAddCommand($composer);
        $tester = new CommandTester($command);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('--no-squash', $output);
    }

    public function testItUsesSquashFlagWhenOptionProvided(): void
    {
        $composer = $this->createMock(Composer::class);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getExtra')->willReturn(['subtrees' => []]);
        $composer->method('getPackage')->willReturn($package);

        $command = new SubtreeAddCommand($composer);
        $tester = new CommandTester($command);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
            '--squash' => true,
        ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('--squash', $output);
    }
}