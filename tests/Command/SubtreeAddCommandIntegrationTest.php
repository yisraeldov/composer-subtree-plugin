<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Git\GitProcessResult;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SubtreeAddCommandIntegrationTest extends TestCase
{
    public function testItRunsGitSubtreeAddCommand(): void
    {
        $tempDir = sys_get_temp_dir() . '/composer-subtree-plugin-tests-' . uniqid('', true);
        mkdir($tempDir, 0777, true);
        $composerJsonPath = $tempDir . '/composer.json';

        file_put_contents(
            $composerJsonPath,
            json_encode(['name' => 'acme/app'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $composer = $this->createMock(Composer::class);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getExtra')->willReturn(['subtrees' => []]);
        $composer->method('getPackage')->willReturn($package);

        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        $command = new SubtreeAddCommand($composer, $gitRunner, $composerJsonPath);
        $tester = new CommandTester($command);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
            'prefix' => 'packages/pcre',
        ]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
