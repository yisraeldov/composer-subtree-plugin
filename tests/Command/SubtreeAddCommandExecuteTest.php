<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Git\GitProcessException;
use ComposerSubtreePlugin\Git\GitProcessResult;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SubtreeAddCommandExecuteTest extends TestCase
{
    private const ADD_PCRE_GIT
        = "git subtree add --prefix='packages/pcre' "
        . "'https://github.com/composer/pcre.git' 'main'";
    private const ADD_PCRE_NO_GIT
        = "git subtree add --prefix='packages/pcre' "
        . "'https://github.com/composer/pcre' 'main'";
    private const ADD_SCP_HOOPLA
        = "git subtree add --prefix='packages/hoopla-payroll' "
        . "'git@github.com:Behavior-Analyst-Professional-Services"
        . "/hoopla-payroll.git' 'master' --squash";

    public function testItDefaultsPrefixToPackagesRepoName(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with(self::ADD_PCRE_GIT)
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester] = $this->createIsolatedCommandTester($gitRunner);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('packages/pcre', $output);
    }

    public function testItDefaultsSquashToFalse(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with(self::ADD_PCRE_GIT)
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester] = $this->createIsolatedCommandTester($gitRunner);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testItUsesSquashFlagWhenOptionProvided(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with(self::ADD_PCRE_GIT . ' --squash')
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester] = $this->createIsolatedCommandTester($gitRunner);

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
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
            ['name' => 'acme/app', 'extra' => ['subtrees' => []]],
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        $composerManifest = $this->readComposerManifest($composerJsonPath);

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

    public function testItAddsPathRepositoryForAddedSubtree(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
            ['name' => 'acme/app', 'extra' => ['subtrees' => []]],
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        $composerManifest = $this->readComposerManifest($composerJsonPath);
        $repositories = $this->readRepositories($composerManifest);

        self::assertContains(
            ['type' => 'path', 'url' => 'packages/pcre'],
            $repositories,
        );
    }

    public function testItPreservesRepositoriesWhenAddingPathRepository(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
            [
                'name' => 'acme/app',
                'repositories' => [
                    [
                        'type' => 'vcs',
                        'url' => 'https://github.com/acme/private-package.git',
                    ],
                ],
            ],
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        $composerManifest = $this->readComposerManifest($composerJsonPath);
        $repositories = $this->readRepositories($composerManifest);

        self::assertContains(
            [
                'type' => 'vcs',
                'url' => 'https://github.com/acme/private-package.git',
            ],
            $repositories,
        );
        self::assertContains(
            ['type' => 'path', 'url' => 'packages/pcre'],
            $repositories,
        );
    }

    public function testItAvoidsDuplicatePathRepository(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
            [
                'name' => 'acme/app',
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => 'packages/pcre',
                    ],
                ],
            ],
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        $composerManifest = $this->readComposerManifest($composerJsonPath);
        $repositories = $this->readRepositories($composerManifest);

        self::assertSame(
            1,
            $this->countPathRepositories($repositories, 'packages/pcre'),
        );
    }

    public function testItPersistsSquashFlagWhenProvided(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
            ['name' => 'acme/app'],
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
            '--squash' => true,
        ]);

        $composerManifest = $this->readComposerManifest($composerJsonPath);
        $subtree = $this->readSubtreeEntry($composerManifest, 'composer/pcre');

        self::assertTrue(
            (bool) ($subtree['squash'] ?? false),
        );
    }

    public function testItRejectsSubtreeKeyContainingDot(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::never())->method('runOrFail');

        [$tester] = $this->createIsolatedCommandTester(
            $gitRunner,
            ['name' => 'acme/app'],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot contain dots');

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/p.cre.git',
            'upstream-branch' => 'main',
        ]);
    }

    public function testItCanPersistToInjectedComposerJsonPath(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $tempComposerJson] = $this->createIsolatedCommandTester(
            $gitRunner,
            ['name' => 'acme/app'],
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        $composerManifest = $this->readComposerManifest($tempComposerJson);

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

    public function testItPreservesExistingSubtreesWhenAddingAnother(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with(self::ADD_PCRE_GIT)
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
            [
                'name' => 'acme/app',
                'extra' => [
                    'subtrees' => [
                        'psr/log' => [
                            'package' => 'psr/log',
                            'prefix' => 'packages/log',
                            'remote' => 'https://github.com/php-fig/log.git',
                            'branch' => 'master',
                            'squash' => true,
                        ],
                    ],
                ],
            ],
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        $composerManifest = $this->readComposerManifest($composerJsonPath);
        $subtrees = $this->readSubtrees($composerManifest);

        self::assertArrayHasKey('psr/log', $subtrees);
        self::assertArrayHasKey('composer/pcre', $subtrees);
        self::assertSame(
            'packages/log',
            $subtrees['psr/log']['prefix'] ?? null,
        );
        self::assertSame(
            'packages/pcre',
            $subtrees['composer/pcre']['prefix'] ?? null,
        );
    }

    public function testItDerivesPackageAndPrefixWhenUrlHasNoGitSuffix(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with(self::ADD_PCRE_NO_GIT)
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre',
            'upstream-branch' => 'main',
        ]);

        $composerManifest = $this->readComposerManifest($composerJsonPath);

        self::assertSame(
            [
                'package' => 'composer/pcre',
                'prefix' => 'packages/pcre',
                'remote' => 'https://github.com/composer/pcre',
                'branch' => 'main',
                'squash' => false,
            ],
            $this->readSubtreeEntry($composerManifest, 'composer/pcre'),
        );
    }

    public function testItDerivesPackageNameFromScpStyleGitRemote(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with(self::ADD_SCP_HOOPLA)
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
        );

        $tester->execute([
            'upstream-url'
                => 'git@github.com:Behavior-Analyst-Professional-Services'
                . '/hoopla-payroll.git',
            'upstream-branch' => 'master',
            'prefix' => 'packages/hoopla-payroll',
            '--squash' => true,
        ]);

        $composerManifest = $this->readComposerManifest($composerJsonPath);

        self::assertSame(
            [
                'package'
                    => 'Behavior-Analyst-Professional-Services/hoopla-payroll',
                'prefix' => 'packages/hoopla-payroll',
                'remote'
                    => 'git@github.com:Behavior-Analyst-Professional-Services'
                    . '/hoopla-payroll.git',
                'branch' => 'master',
                'squash' => true,
            ],
            $this->readSubtreeEntry(
                $composerManifest,
                'Behavior-Analyst-Professional-Services/hoopla-payroll',
            ),
        );
    }

    public function testItDoesNotPersistConfigWhenGitCommandFails(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with(self::ADD_PCRE_GIT)
            ->willThrowException(
                new GitProcessException(
                    'Git command failed',
                    self::ADD_PCRE_GIT,
                    1,
                    'fatal',
                ),
            );

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
            ['name' => 'acme/app'],
        );

        try {
            $tester->execute([
                'upstream-url' => 'https://github.com/composer/pcre.git',
                'upstream-branch' => 'main',
            ]);
            self::fail('Expected git failure exception was not thrown.');
        } catch (GitProcessException) {
            $composerManifest = $this->readComposerManifest($composerJsonPath);
            self::assertArrayNotHasKey('extra', $composerManifest);
        }
    }

    public function testItEscapesShellArgumentsInGitSubtreeAddCommand(): void
    {
        $commands = [];
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->willReturnCallback(
                function (string $command) use (&$commands): GitProcessResult {
                    $commands[] = $command;

                    return new GitProcessResult(0, '', '');
                },
            );

        [$tester] = $this->createIsolatedCommandTester($gitRunner);

        $tester->execute([
            'upstream-url' => 'ssh:composer/pcre',
            'upstream-branch' => 'main;echo hacked',
            'prefix' => 'packages/pcre;echo hacked',
        ]);

        $expectedCommand
            = "git subtree add --prefix='packages/pcre;echo hacked' "
            . "'ssh:composer/pcre' 'main;echo hacked'";

        self::assertSame($expectedCommand, $commands[0] ?? null);
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array{0: CommandTester, 1: string}
     */
    private function createIsolatedCommandTester(
        GitProcessRunner $gitRunner,
        array $manifest = ['name' => 'acme/app'],
    ): array {
        $tempDir = $this->createTempDirectory();
        $composerJsonPath = $tempDir . '/composer.json';
        $encoded = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );

        self::assertIsString($encoded);

        file_put_contents($composerJsonPath, $encoded . "\n");

        $composer = $this->createComposerWithEmptySubtrees();
        $command = new SubtreeAddCommand(
            $composer,
            $gitRunner,
            $composerJsonPath,
        );

        return [new CommandTester($command), $composerJsonPath];
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
        $subtrees = $this->readSubtrees($manifest);

        $entry = $subtrees[$key] ?? null;
        self::assertIsArray($entry);

        $normalized = [];

        foreach ($entry as $entryKey => $entryValue) {
            $normalized[$entryKey] = $entryValue;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array<string, array<string, mixed>>
     */
    private function readSubtrees(array $manifest): array
    {
        $extra = $manifest['extra'] ?? null;
        self::assertIsArray($extra);

        $subtrees = $extra['subtrees'] ?? null;
        self::assertIsArray($subtrees);

        return array_reduce(
            array_keys($subtrees),
            fn(array $carry, mixed $subtreeKey): array
                => $this->appendNormalizedSubtree(
                    $carry,
                    $subtrees,
                    $subtreeKey,
                ),
            [],
        );
    }

    /**
     * @param array<string, array<string, mixed>> $carry
     * @param array<mixed> $subtrees
     *
     * @return array<string, array<string, mixed>>
     */
    private function appendNormalizedSubtree(
        array $carry,
        array $subtrees,
        mixed $subtreeKey,
    ): array {
        if (!is_string($subtreeKey)) {
            return $carry;
        }

        $subtreeValue = $subtrees[$subtreeKey] ?? null;

        if (!is_array($subtreeValue)) {
            return $carry;
        }

        $carry[$subtreeKey] = $this->normalizeEntry($subtreeValue);

        return $carry;
    }

    /**
     * @param array<mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function normalizeEntry(array $entry): array
    {
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
        $path = sprintf(
            '%s/composer-subtree-plugin-tests-%s',
            sys_get_temp_dir(),
            uniqid('', true),
        );
        mkdir($path, 0777, true);

        return $path;
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array<int, array<string, mixed>>
     */
    private function readRepositories(array $manifest): array
    {
        $repositories = $manifest['repositories'] ?? null;

        if (!is_array($repositories)) {
            return [];
        }

        $normalizedRepositories = [];

        foreach ($repositories as $repository) {
            if (!is_array($repository)) {
                continue;
            }

            $normalizedRepositories[] = $this->normalizeEntry($repository);
        }

        return $normalizedRepositories;
    }

    /**
     * @param array<int, array<string, mixed>> $repositories
     */
    private function countPathRepositories(
        array $repositories,
        string $url,
    ): int {
        return count(
            array_filter(
                $repositories,
                static fn(array $repository): bool
                    => ($repository['type'] ?? null) === 'path'
                        && ($repository['url'] ?? null) === $url,
            ),
        );
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
