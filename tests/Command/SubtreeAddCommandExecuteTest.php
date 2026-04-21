<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Git\GitProcessResult;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SubtreeAddCommandExecuteTest extends TestCase
{
    private string $originalCwd;

    protected function setUp(): void
    {
        $cwd = getcwd();

        self::assertNotFalse($cwd);

        $this->originalCwd = $cwd;
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
    }

    public function testItDefaultsPrefixToPackagesRepoName(): void
    {
        $composer = $this->createComposerWithEmptySubtrees();
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with('git subtree add --prefix=packages/pcre https://github.com/composer/pcre.git main')
            ->willReturn(new GitProcessResult(0, '', ''));

        $command = new SubtreeAddCommand($composer, $gitRunner);
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
        $composer = $this->createComposerWithEmptySubtrees();
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with('git subtree add --prefix=packages/pcre https://github.com/composer/pcre.git main')
            ->willReturn(new GitProcessResult(0, '', ''));

        $command = new SubtreeAddCommand($composer, $gitRunner);
        $tester = new CommandTester($command);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testItUsesSquashFlagWhenOptionProvided(): void
    {
        $composer = $this->createComposerWithEmptySubtrees();
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with('git subtree add --prefix=packages/pcre https://github.com/composer/pcre.git main --squash')
            ->willReturn(new GitProcessResult(0, '', ''));

        $command = new SubtreeAddCommand($composer, $gitRunner);
        $tester = new CommandTester($command);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
            '--squash' => true,
        ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('--squash', $output);
    }

    public function testItPersistsSubtreeConfigToComposerJson(): void
    {
        $tempDir = $this->createTempDirectory();
        chdir($tempDir);

        file_put_contents(
            $tempDir . '/composer.json',
            json_encode(['name' => 'acme/app', 'extra' => ['subtrees' => []]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $composer = $this->createComposerWithEmptySubtrees();
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        $command = new SubtreeAddCommand($composer, $gitRunner);
        $tester = new CommandTester($command);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        $composerManifest = $this->readComposerManifest($tempDir . '/composer.json');

        self::assertSame(
            [
                'package' => 'composer/pcre',
                'prefix' => 'packages/pcre',
                'remote' => 'https://github.com/composer/pcre.git',
                'branch' => 'main',
                'squash' => false,
            ],
            $this->readSubtreeEntry($composerManifest, 'composer/pcre'),
        );
    }

    public function testItPersistsSquashFlagWhenProvided(): void
    {
        $tempDir = $this->createTempDirectory();
        chdir($tempDir);

        file_put_contents(
            $tempDir . '/composer.json',
            json_encode(['name' => 'acme/app'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $composer = $this->createComposerWithEmptySubtrees();
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        $command = new SubtreeAddCommand($composer, $gitRunner);
        $tester = new CommandTester($command);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
            '--squash' => true,
        ]);

        $composerManifest = $this->readComposerManifest($tempDir . '/composer.json');
        $subtree = $this->readSubtreeEntry($composerManifest, 'composer/pcre');

        self::assertTrue(
            (bool) ($subtree['squash'] ?? false),
        );
    }

    public function testItRejectsSubtreeKeyContainingDot(): void
    {
        $tempDir = $this->createTempDirectory();
        chdir($tempDir);

        file_put_contents(
            $tempDir . '/composer.json',
            json_encode(['name' => 'acme/app'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $composer = $this->createComposerWithEmptySubtrees();
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::never())->method('runOrFail');

        $command = new SubtreeAddCommand($composer, $gitRunner);
        $tester = new CommandTester($command);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot contain dots');

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/p.cre.git',
            'upstream-branch' => 'main',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerManifest(string $path): array
    {
        $content = file_get_contents($path);

        self::assertNotFalse($content);

        $decoded = json_decode($content, true);

        self::assertIsArray($decoded);

        $manifest = [];

        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $manifest[$key] = $value;
        }

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array<string, mixed>
     */
    private function readSubtreeEntry(array $manifest, string $key): array
    {
        $extra = $manifest['extra'] ?? null;
        self::assertIsArray($extra);

        $subtrees = $extra['subtrees'] ?? null;
        self::assertIsArray($subtrees);

        $entry = $subtrees[$key] ?? null;
        self::assertIsArray($entry);

        $normalized = [];

        foreach ($entry as $entryKey => $entryValue) {
            if (!is_string($entryKey)) {
                continue;
            }

            $normalized[$entryKey] = $entryValue;
        }

        return $normalized;
    }

    private function createTempDirectory(): string
    {
        $path = sys_get_temp_dir() . '/composer-subtree-plugin-tests-' . uniqid('', true);
        mkdir($path, 0777, true);

        return $path;
    }

    private function createComposerWithEmptySubtrees(): Composer
    {
        $composer = $this->createMock(Composer::class);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getExtra')->willReturn(['subtrees' => []]);
        $composer->method('getPackage')->willReturn($package);

        return $composer;
    }
}
