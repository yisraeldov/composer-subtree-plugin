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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SubtreeAddCommandExecuteTest extends TestCase
{
    private const ADD_PCRE_GIT
        = "git subtree add --prefix='packages/pcre' "
        . "'https://github.com/composer/pcre.git' 'main'";
    private const ADD_ESCAPED_ARGUMENTS
        = "git subtree add --prefix='packages/pcre' "
        . "'ssh:composer/pcre' 'main;echo hacked'";

    public function testItDefaultsPrefixToPackagesRepoName(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())
            ->method('runOrFail')
            ->with(self::ADD_PCRE_GIT)
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester] = $this->createIsolatedCommandTester($gitRunner);

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
        ]);

        self::assertStringContainsString(
            'packages/pcre',
            $tester->getDisplay(),
        );
    }

    public function testItPersistsSubtreeMetadataOnPathRepository(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())
            ->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
            ['name' => 'acme/app'],
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
            'prefix' => 'packages/pcre',
        ]);

        $manifest = $this->readComposerManifest($composerJsonPath);

        self::assertFalse($this->hasLegacySubtrees($manifest));
        self::assertSame(
            [
                'type' => 'path',
                'url' => 'packages/pcre',
                'composer-subtree-plugin' => [
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                    'squash' => false,
                ],
            ],
            $this->readPathRepository($manifest, 'packages/pcre'),
        );
    }

    public function testItUpdatesPathRepositoryWithoutDuplication(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())
            ->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
            [
                'name' => 'acme/app',
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => 'packages/pcre',
                        'options' => ['symlink' => true],
                    ],
                ],
            ],
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
            'prefix' => 'packages/pcre',
            '--squash' => true,
        ]);

        $manifest = $this->readComposerManifest($composerJsonPath);
        $repositories = $this->readRepositories($manifest);

        self::assertCount(1, $repositories);
        self::assertSame(
            [
                'type' => 'path',
                'url' => 'packages/pcre',
                'options' => ['symlink' => true],
                'composer-subtree-plugin' => [
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                    'squash' => true,
                ],
            ],
            $this->readPathRepository($manifest, 'packages/pcre'),
        );
    }

    public function testItPreservesUnrelatedRepositoriesOnAppend(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())
            ->method('runOrFail')
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester, $composerJsonPath] = $this->createIsolatedCommandTester(
            $gitRunner,
            [
                'name' => 'acme/app',
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://example.org/packages.json',
                    ],
                ],
            ],
        );

        $tester->execute([
            'upstream-url' => 'https://github.com/composer/pcre.git',
            'upstream-branch' => 'main',
            'prefix' => 'packages/pcre',
        ]);

        $manifest = $this->readComposerManifest($composerJsonPath);
        $repositories = $this->readRepositories($manifest);

        self::assertCount(2, $repositories);
        self::assertSame(
            [
                'type' => 'composer',
                'url' => 'https://example.org/packages.json',
            ],
            $repositories[0],
        );
        $secondRepository = $repositories[1] ?? null;
        self::assertIsArray($secondRepository);
        self::assertSame(
            'packages/pcre',
            $secondRepository['url'] ?? null,
        );
    }

    public function testItDoesNotPersistConfigWhenGitCommandFails(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())
            ->method('runOrFail')
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
            [
                'name' => 'acme/app',
                'repositories' => [
                    [
                        'type' => 'composer',
                        'url' => 'https://example.org/packages.json',
                    ],
                ],
            ],
        );

        $this->expectException(GitProcessException::class);

        try {
            $tester->execute([
                'upstream-url' => 'https://github.com/composer/pcre.git',
                'upstream-branch' => 'main',
                'prefix' => 'packages/pcre',
            ]);
        } finally {
            $manifest = $this->readComposerManifest($composerJsonPath);
            $repositories = $this->readRepositories($manifest);

            self::assertCount(1, $repositories);
            self::assertFalse($this->hasLegacySubtrees($manifest));
        }
    }

    public function testItEscapesShellArgumentsInGitSubtreeAddCommand(): void
    {
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::once())->method('runOrFail')
            ->with(self::ADD_ESCAPED_ARGUMENTS)
            ->willReturn(new GitProcessResult(0, '', ''));

        [$tester] = $this->createIsolatedCommandTester($gitRunner);

        $statusCode = $tester->execute([
            'upstream-url' => 'ssh:composer/pcre',
            'upstream-branch' => 'main;echo hacked',
            'prefix' => 'packages/pcre',
        ]);

        self::assertSame(Command::SUCCESS, $statusCode);
    }

    /**
     * @param array<string, mixed>|null $manifest
     *
     * @return array{0: CommandTester, 1: string}
     */
    private function createIsolatedCommandTester(
        GitProcessRunner $gitRunner,
        ?array $manifest = null,
    ): array {
        $tempDir = sprintf(
            '%s/composer-subtree-plugin-tests-%s',
            sys_get_temp_dir(),
            uniqid('', true),
        );
        mkdir($tempDir, 0777, true);

        $composerJsonPath = $tempDir . '/composer.json';
        $seedManifest = $manifest ?? ['name' => 'acme/app'];

        file_put_contents(
            $composerJsonPath,
            json_encode(
                $seedManifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );

        $composer = $this->createMock(Composer::class);
        $package = $this->createMock(RootPackageInterface::class);
        $composer->method('getPackage')->willReturn($package);

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
    private function readComposerManifest(string $composerJsonPath): array
    {
        $contents = file_get_contents($composerJsonPath);

        self::assertIsString($contents);

        $decoded = json_decode($contents, true);

        self::assertIsArray($decoded);

        return $this->normalizeEntry($decoded);
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array<int, array<string, mixed>>
     */
    private function readRepositories(array $manifest): array
    {
        $repositories = $manifest['repositories'] ?? [];

        if (!is_array($repositories)) {
            return [];
        }

        return array_values(
            array_reduce(
                $repositories,
                fn(array $carry, mixed $repository): array
                    => $this->appendRepository($carry, $repository),
                [],
            ),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $carry
     *
     * @return array<int, array<string, mixed>>
     */
    private function appendRepository(array $carry, mixed $repository): array
    {
        if (!is_array($repository)) {
            return $carry;
        }

        $carry[] = $this->normalizeEntry($repository);

        return $carry;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function hasLegacySubtrees(array $manifest): bool
    {
        $extra = $manifest['extra'] ?? null;

        if (!is_array($extra)) {
            return false;
        }

        return array_key_exists('subtrees', $extra);
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return array<string, mixed>
     */
    private function readPathRepository(array $manifest, string $prefix): array
    {
        $repositories = $this->readRepositories($manifest);

        $matchingRepositories = array_values(
            array_filter(
                $repositories,
                fn(mixed $repository): bool
                    => $this->isMatchingPathRepository($repository, $prefix),
            ),
        );

        self::assertNotEmpty(
            $matchingRepositories,
            'Path repository not found for prefix: ' . $prefix,
        );

        return $matchingRepositories[0];
    }

    private function isMatchingPathRepository(
        mixed $repository,
        string $prefix,
    ): bool {
        if (!is_array($repository)) {
            return false;
        }

        return ($repository['type'] ?? null) === 'path'
            && ($repository['url'] ?? null) === $prefix;
    }

    /**
     * @param array<mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function normalizeEntry(array $entry): array
    {
        return array_reduce(
            array_keys($entry),
            fn(array $carry, mixed $key): array
                => $this->appendStringKeyedEntry($carry, $entry, $key),
            [],
        );
    }

    /**
     * @param array<string, mixed> $carry
     * @param array<mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function appendStringKeyedEntry(
        array $carry,
        array $entry,
        mixed $key,
    ): array {
        if (!is_string($key)) {
            return $carry;
        }

        $carry[$key] = $entry[$key] ?? null;

        return $carry;
    }
}
