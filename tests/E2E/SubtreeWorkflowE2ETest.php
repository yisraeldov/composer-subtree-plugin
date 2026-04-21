<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\E2E;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Command\SubtreePullCommand;
use ComposerSubtreePlugin\Command\SubtreePushCommand;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

final class SubtreeWorkflowE2ETest extends TestCase
{
    private string $projectComposerPath;
    private string $projectComposerHash;

    protected function setUp(): void
    {
        $this->projectComposerPath = dirname(__DIR__, 2) . '/composer.json';
        $hash = hash_file('sha256', $this->projectComposerPath);

        self::assertIsString($hash);

        $this->projectComposerHash = $hash;
    }

    protected function tearDown(): void
    {
        $currentHash = hash_file('sha256', $this->projectComposerPath);

        self::assertIsString($currentHash);
        self::assertSame(
            $this->projectComposerHash,
            $currentHash,
            'Project composer.json changed during E2E tests.',
        );
    }

    public function testAddPullPushWorkflowInIsolatedRepos(): void
    {
        if (!$this->isGitSubtreeAvailable()) {
            self::markTestSkipped('git subtree is not available in PATH.');
        }

        $sandbox = $this->createSandboxDirectory();

        try {
            $remotePath = $sandbox . '/upstream-remote';
            $seedPath = $sandbox . '/upstream-seed';
            $maintainerPath = $sandbox . '/upstream-maintainer';
            $consumerPath = $sandbox . '/consumer';
            $verifyPath = $sandbox . '/verify';
            $composerJsonPath = $consumerPath . '/composer.json';

            $this->initializeRemoteWithSeedCommit($remotePath, $seedPath);
            $this->initializeConsumerRepository($consumerPath, $composerJsonPath);

            $addStatus = $this->runInDirectory(
                $consumerPath,
                function () use ($composerJsonPath, $remotePath): int {
                    $tester = new CommandTester(
                        new SubtreeAddCommand(
                            $this->createComposerWithSubtrees([]),
                            new GitProcessRunner(),
                            $composerJsonPath,
                        ),
                    );

                    return $tester->execute([
                        'upstream-url' => $remotePath,
                        'upstream-branch' => 'main',
                        'prefix' => 'packages/library',
                    ]);
                },
            );

            self::assertSame(Command::SUCCESS, $addStatus);
            self::assertFileExists($consumerPath . '/packages/library/upstream.txt');
            self::assertSame(
                "v1\n",
                file_get_contents($consumerPath . '/packages/library/upstream.txt'),
            );

            $subtrees = $this->readSubtreesFromComposerManifest($composerJsonPath);
            self::assertCount(1, $subtrees);

            $this->runGitCommand('git add .', $consumerPath);
            $this->runGitCommand(
                "git commit -m 'record subtree add result'",
                $consumerPath,
            );

            $this->addUpstreamCommit($remotePath, $maintainerPath);

            $pullStatus = $this->runInDirectory(
                $consumerPath,
                function () use ($subtrees): int {
                    $tester = new CommandTester(
                        new SubtreePullCommand(
                            $this->createComposerWithSubtrees($subtrees),
                            new GitProcessRunner(),
                        ),
                    );

                    return $tester->execute(['target' => 'all']);
                },
            );

            self::assertSame(Command::SUCCESS, $pullStatus);
            self::assertSame(
                "v2\n",
                file_get_contents($consumerPath . '/packages/library/upstream.txt'),
            );

            file_put_contents(
                $consumerPath . '/packages/library/consumer.txt',
                "from consumer\n",
            );
            $this->runGitCommand(
                'git add packages/library/consumer.txt',
                $consumerPath,
            );
            $this->runGitCommand(
                "git commit -m 'add consumer subtree file'",
                $consumerPath,
            );

            $pushStatus = $this->runInDirectory(
                $consumerPath,
                function () use ($subtrees): int {
                    $tester = new CommandTester(
                        new SubtreePushCommand(
                            $this->createComposerWithSubtrees($subtrees),
                            new GitProcessRunner(),
                        ),
                    );

                    return $tester->execute(['target' => 'all']);
                },
            );

            self::assertSame(Command::SUCCESS, $pushStatus);

            $this->runGitCommand(
                sprintf('git clone %s %s', escapeshellarg($remotePath), escapeshellarg($verifyPath)),
                $sandbox,
            );

            self::assertFileExists($verifyPath . '/consumer.txt');
            self::assertSame(
                "from consumer\n",
                file_get_contents($verifyPath . '/consumer.txt'),
            );
        } finally {
            $this->removeDirectoryRecursively($sandbox);
        }
    }

    public function testGitHubSmokeAddAndPullWhenEnabled(): void
    {
        if (getenv('SUBTREE_GITHUB_SMOKE') !== '1') {
            self::markTestSkipped('Set SUBTREE_GITHUB_SMOKE=1 to run GitHub smoke test.');
        }

        if (!$this->isGitSubtreeAvailable()) {
            self::markTestSkipped('git subtree is not available in PATH.');
        }

        $remote = getenv('SUBTREE_GITHUB_REMOTE');
        $branch = getenv('SUBTREE_GITHUB_BRANCH');
        $sandbox = $this->createSandboxDirectory();

        try {
            $consumerPath = $sandbox . '/consumer';
            $composerJsonPath = $consumerPath . '/composer.json';
            $upstreamRemote = is_string($remote) && $remote !== ''
                ? $remote
                : 'https://github.com/composer/pcre.git';
            $upstreamBranch = is_string($branch) && $branch !== ''
                ? $branch
                : 'main';

            $this->initializeConsumerRepository($consumerPath, $composerJsonPath);

            $addStatus = $this->runInDirectory(
                $consumerPath,
                function () use (
                    $composerJsonPath,
                    $upstreamRemote,
                    $upstreamBranch,
                ): int {
                    $tester = new CommandTester(
                        new SubtreeAddCommand(
                            $this->createComposerWithSubtrees([]),
                            new GitProcessRunner(),
                            $composerJsonPath,
                        ),
                    );

                    return $tester->execute([
                        'upstream-url' => $upstreamRemote,
                        'upstream-branch' => $upstreamBranch,
                        'prefix' => 'packages/pcre',
                    ]);
                },
            );

            self::assertSame(Command::SUCCESS, $addStatus);
            self::assertFileExists($consumerPath . '/packages/pcre/composer.json');

            $subtrees = $this->readSubtreesFromComposerManifest($composerJsonPath);

            $pullStatus = $this->runInDirectory(
                $consumerPath,
                function () use ($subtrees): int {
                    $tester = new CommandTester(
                        new SubtreePullCommand(
                            $this->createComposerWithSubtrees($subtrees),
                            new GitProcessRunner(),
                        ),
                    );

                    return $tester->execute(['target' => 'all']);
                },
            );

            self::assertSame(Command::SUCCESS, $pullStatus);
        } finally {
            $this->removeDirectoryRecursively($sandbox);
        }
    }

    public function testGitHubSmokePushWhenEnabled(): void
    {
        if (getenv('SUBTREE_GITHUB_PUSH_SMOKE') !== '1') {
            self::markTestSkipped(
                'Set SUBTREE_GITHUB_PUSH_SMOKE=1 to run GitHub push smoke test.',
            );
        }

        if (!$this->isGitSubtreeAvailable()) {
            self::markTestSkipped('git subtree is not available in PATH.');
        }

        $remote = getenv('SUBTREE_GITHUB_REMOTE');
        $branch = getenv('SUBTREE_GITHUB_BRANCH');

        if (!is_string($remote) || $remote === '') {
            self::markTestSkipped(
                'SUBTREE_GITHUB_REMOTE must be set for push smoke test.',
            );
        }

        if (!is_string($branch) || $branch === '') {
            self::markTestSkipped(
                'SUBTREE_GITHUB_BRANCH must be set for push smoke test.',
            );
        }

        $sandbox = $this->createSandboxDirectory();

        try {
            $consumerPath = $sandbox . '/consumer';
            $verifyPath = $sandbox . '/verify';
            $composerJsonPath = $consumerPath . '/composer.json';

            $this->initializeConsumerRepository($consumerPath, $composerJsonPath);

            $addStatus = $this->runInDirectory(
                $consumerPath,
                function () use ($composerJsonPath, $remote, $branch): int {
                    $tester = new CommandTester(
                        new SubtreeAddCommand(
                            $this->createComposerWithSubtrees([]),
                            new GitProcessRunner(),
                            $composerJsonPath,
                        ),
                    );

                    return $tester->execute([
                        'upstream-url' => $remote,
                        'upstream-branch' => $branch,
                        'prefix' => 'packages/smoke-push',
                    ]);
                },
            );

            self::assertSame(Command::SUCCESS, $addStatus);

            $subtrees = $this->readSubtreesFromComposerManifest($composerJsonPath);
            self::assertCount(1, $subtrees);

            $fileName = sprintf('smoke-%s.txt', bin2hex(random_bytes(5)));

            file_put_contents(
                $consumerPath . '/packages/smoke-push/' . $fileName,
                "github push smoke\n",
            );
            $this->runGitCommand(
                sprintf(
                    'git add %s',
                    escapeshellarg('packages/smoke-push/' . $fileName),
                ),
                $consumerPath,
            );
            $this->runGitCommand(
                "git commit -m 'add github push smoke file'",
                $consumerPath,
            );

            $pushStatus = $this->runInDirectory(
                $consumerPath,
                function () use ($subtrees): int {
                    $tester = new CommandTester(
                        new SubtreePushCommand(
                            $this->createComposerWithSubtrees($subtrees),
                            new GitProcessRunner(),
                        ),
                    );

                    return $tester->execute(['target' => 'all']);
                },
            );

            self::assertSame(Command::SUCCESS, $pushStatus);

            $this->runGitCommand(
                sprintf('git clone %s %s', escapeshellarg($remote), escapeshellarg($verifyPath)),
                $sandbox,
            );
            $this->runGitCommand(
                sprintf('git checkout %s', escapeshellarg($branch)),
                $verifyPath,
            );

            self::assertFileExists($verifyPath . '/' . $fileName);
            self::assertSame(
                "github push smoke\n",
                file_get_contents($verifyPath . '/' . $fileName),
            );
        } finally {
            $this->removeDirectoryRecursively($sandbox);
        }
    }

    private function initializeRemoteWithSeedCommit(
        string $remotePath,
        string $seedPath,
    ): void {
        mkdir($remotePath, 0777, true);
        mkdir($seedPath, 0777, true);

        $this->runGitCommand('git init --bare', $remotePath);
        $this->runGitCommand('git init', $seedPath);
        $this->configureLocalGitIdentity($seedPath);

        file_put_contents($seedPath . '/upstream.txt', "v1\n");

        $this->runGitCommand('git add upstream.txt', $seedPath);
        $this->runGitCommand("git commit -m 'seed upstream'", $seedPath);
        $this->runGitCommand('git branch -M main', $seedPath);
        $this->runGitCommand(
            sprintf('git remote add origin %s', escapeshellarg($remotePath)),
            $seedPath,
        );
        $this->runGitCommand('git push -u origin main', $seedPath);
        $this->runGitCommand('git symbolic-ref HEAD refs/heads/main', $remotePath);
    }

    private function initializeConsumerRepository(
        string $consumerPath,
        string $composerJsonPath,
    ): void {
        mkdir($consumerPath, 0777, true);

        $this->runGitCommand('git init', $consumerPath);
        $this->configureLocalGitIdentity($consumerPath);

        file_put_contents($consumerPath . '/README.md', "# Consumer\n");
        file_put_contents(
            $composerJsonPath,
            json_encode(
                [
                    'name' => 'acme/consumer',
                    'extra' => ['subtrees' => []],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );

        $this->runGitCommand('git add README.md composer.json', $consumerPath);
        $this->runGitCommand("git commit -m 'seed consumer'", $consumerPath);
    }

    private function addUpstreamCommit(string $remotePath, string $maintainerPath): void
    {
        $this->runGitCommand(
            sprintf('git clone %s %s', escapeshellarg($remotePath), escapeshellarg($maintainerPath)),
            dirname($maintainerPath),
        );
        $this->configureLocalGitIdentity($maintainerPath);

        file_put_contents($maintainerPath . '/upstream.txt', "v2\n");

        $this->runGitCommand('git add upstream.txt', $maintainerPath);
        $this->runGitCommand("git commit -m 'update upstream'", $maintainerPath);
        $this->runGitCommand('git push origin main', $maintainerPath);
    }

    private function configureLocalGitIdentity(string $repositoryPath): void
    {
        $this->runGitCommand("git config user.name 'Subtree Test'", $repositoryPath);
        $this->runGitCommand(
            "git config user.email 'subtree-test@example.com'",
            $repositoryPath,
        );
    }

    /**
     * @param callable(): int $callback
     */
    private function runInDirectory(string $directory, callable $callback): int
    {
        $originalWorkingDirectory = getcwd();

        self::assertIsString($originalWorkingDirectory);

        chdir($directory);

        try {
            return $callback();
        } finally {
            chdir($originalWorkingDirectory);
        }
    }

    private function runGitCommand(string $command, string $workingDirectory): void
    {
        $process = Process::fromShellCommandline($command, $workingDirectory);
        $process->run();

        if ($process->isSuccessful()) {
            return;
        }

        self::fail(
            sprintf(
                "Git command failed:\ncommand: %s\ncwd: %s\nstdout: %s\nstderr: %s",
                $command,
                $workingDirectory,
                $process->getOutput(),
                $process->getErrorOutput(),
            ),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readSubtreesFromComposerManifest(string $composerJsonPath): array
    {
        $contents = file_get_contents($composerJsonPath);

        self::assertIsString($contents);

        $decoded = json_decode($contents, true);

        self::assertIsArray($decoded);

        $extra = $decoded['extra'] ?? null;
        self::assertIsArray($extra);

        $subtrees = $extra['subtrees'] ?? null;
        self::assertIsArray($subtrees);

        return array_reduce(
            array_keys($subtrees),
            fn(array $carry, mixed $key): array
                => $this->appendSubtreeConfiguration($carry, $subtrees, $key),
            [],
        );
    }

    /**
     * @param array<string, array<string, mixed>> $carry
     * @param array<mixed> $subtrees
     *
     * @return array<string, array<string, mixed>>
     */
    private function appendSubtreeConfiguration(
        array $carry,
        array $subtrees,
        mixed $key,
    ): array {
        if (!is_string($key)) {
            return $carry;
        }

        $value = $subtrees[$key] ?? null;

        if (!is_array($value)) {
            return $carry;
        }

        $carry[$key] = $this->normalizeSubtreeEntry($value);

        return $carry;
    }

    /**
     * @param array<mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function normalizeSubtreeEntry(array $entry): array
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

    /**
     * @param array<string, array<string, mixed>> $subtrees
     */
    private function createComposerWithSubtrees(array $subtrees): Composer
    {
        $composer = $this->createMock(Composer::class);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getExtra')->willReturn(['subtrees' => $subtrees]);
        $composer->method('getPackage')->willReturn($package);

        return $composer;
    }

    private function isGitSubtreeAvailable(): bool
    {
        $process = Process::fromShellCommandline('git subtree --help');
        $process->run();

        return $process->isSuccessful();
    }

    private function createSandboxDirectory(): string
    {
        $path = sprintf(
            '%s/composer_subtree_plugin_e2e_%s',
            sys_get_temp_dir(),
            bin2hex(random_bytes(6)),
        );

        mkdir($path, 0777, true);

        return $path;
    }

    private function removeDirectoryRecursively(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectoryRecursively($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
