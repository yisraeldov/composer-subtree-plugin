<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\E2E;

use PHPUnit\Framework\TestCase;
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
        $this->requireComposerAndSubtreeTools();

        $sandbox = $this->createSandboxDirectory();

        try {
            $remotePath = $sandbox . '/upstream-remote';
            $seedPath = $sandbox . '/upstream-seed';
            $maintainerPath = $sandbox . '/upstream-maintainer';
            $consumerPath = $sandbox . '/consumer';
            $verifyPath = $sandbox . '/verify';
            $composerJsonPath = $consumerPath . '/composer.json';
            $pluginPath = dirname($this->projectComposerPath);

            $this->initializeRemoteWithSeedCommit($remotePath, $seedPath);
            $this->initializeConsumerRepository(
                $consumerPath,
                $composerJsonPath,
                $pluginPath,
            );

            $this->runComposerCommand(
                'composer install --no-interaction --no-progress',
                $consumerPath,
            );
            $listProcess = $this->runComposerCommand(
                'composer list --raw',
                $consumerPath,
            );
            self::assertStringContainsString(
                'subtree:add',
                $listProcess->getOutput(),
            );

            $this->runComposerCommand(
                sprintf(
                    'composer --no-interaction subtree:add %s '
                    . 'main packages/library',
                    escapeshellarg($remotePath),
                ),
                $consumerPath,
            );

            self::assertFileExists(
                $consumerPath . '/packages/library/upstream.txt',
            );
            self::assertSame(
                "v1\n",
                file_get_contents(
                    $consumerPath . '/packages/library/upstream.txt',
                ),
            );

            $subtrees = $this->readSubtreesFromComposerManifest(
                $composerJsonPath,
            );
            self::assertCount(1, $subtrees);
            self::assertArrayHasKey('packages/library', $subtrees);
            self::assertContainsOnly('array', $subtrees);
            self::assertSame(
                $remotePath,
                $subtrees['packages/library']['remote'] ?? null,
            );

            $this->runGitCommand('git add .', $consumerPath);
            $this->runGitCommand(
                "git commit -m 'record subtree add result'",
                $consumerPath,
            );

            $this->addUpstreamCommit($remotePath, $maintainerPath);

            $this->runComposerCommand(
                'composer --no-interaction subtree:pull all',
                $consumerPath,
            );

            self::assertSame(
                "v2\n",
                file_get_contents(
                    $consumerPath . '/packages/library/upstream.txt',
                ),
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

            $this->runComposerCommand(
                'composer --no-interaction subtree:push all',
                $consumerPath,
            );

            $this->runGitCommand(
                sprintf(
                    'git clone %s %s',
                    escapeshellarg($remotePath),
                    escapeshellarg($verifyPath),
                ),
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
        $this->requireComposerAndSubtreeTools();
        $this->requireSmokeFlag(
            'SUBTREE_GITHUB_SMOKE',
            'Set SUBTREE_GITHUB_SMOKE=1 to run GitHub smoke test.',
        );

        $remote = getenv('SUBTREE_GITHUB_REMOTE');
        $branch = getenv('SUBTREE_GITHUB_BRANCH');
        $sandbox = $this->createSandboxDirectory();

        try {
            $consumerPath = $sandbox . '/consumer';
            $composerJsonPath = $consumerPath . '/composer.json';
            $pluginPath = dirname($this->projectComposerPath);
            $upstreamRemote = is_string($remote) && $remote !== ''
                ? $remote
                : 'https://github.com/composer/pcre.git';
            $upstreamBranch = is_string($branch) && $branch !== ''
                ? $branch
                : 'main';

            $this->initializeConsumerRepository(
                $consumerPath,
                $composerJsonPath,
                $pluginPath,
            );

            $this->runComposerCommand(
                'composer install --no-interaction --no-progress',
                $consumerPath,
            );

            $this->runComposerCommand(
                sprintf(
                    'composer --no-interaction subtree:add %s %s packages/pcre',
                    escapeshellarg($upstreamRemote),
                    escapeshellarg($upstreamBranch),
                ),
                $consumerPath,
            );

            self::assertFileExists(
                $consumerPath . '/packages/pcre/composer.json',
            );

            $this->runComposerCommand(
                'composer --no-interaction subtree:pull all',
                $consumerPath,
            );
        } finally {
            $this->removeDirectoryRecursively($sandbox);
        }
    }

    public function testGitHubSmokePushWhenEnabled(): void
    {
        $this->requireComposerAndSubtreeTools();
        $this->requireSmokeFlag(
            'SUBTREE_GITHUB_PUSH_SMOKE',
            'Set SUBTREE_GITHUB_PUSH_SMOKE=1 to run GitHub push smoke test.',
        );

        [$remote, $branch] = $this->requireGithubPushRemoteAndBranch();

        $sandbox = $this->createSandboxDirectory();

        try {
            $consumerPath = $sandbox . '/consumer';
            $verifyPath = $sandbox . '/verify';
            $composerJsonPath = $consumerPath . '/composer.json';
            $pluginPath = dirname($this->projectComposerPath);

            $this->initializeConsumerRepository(
                $consumerPath,
                $composerJsonPath,
                $pluginPath,
            );

            $this->runComposerCommand(
                'composer install --no-interaction --no-progress',
                $consumerPath,
            );

            $this->runComposerCommand(
                sprintf(
                    'composer --no-interaction subtree:add %s %s '
                    . 'packages/smoke-push',
                    escapeshellarg($remote),
                    escapeshellarg($branch),
                ),
                $consumerPath,
            );

            $subtrees = $this->readSubtreesFromComposerManifest(
                $composerJsonPath,
            );
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

            $this->runComposerCommand(
                'composer --no-interaction subtree:push all',
                $consumerPath,
            );

            $this->runGitCommand(
                sprintf(
                    'git clone %s %s',
                    escapeshellarg($remote),
                    escapeshellarg($verifyPath),
                ),
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
        $this->runGitCommand(
            'git symbolic-ref HEAD refs/heads/main',
            $remotePath,
        );
    }

    private function initializeConsumerRepository(
        string $consumerPath,
        string $composerJsonPath,
        string $pluginPath,
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
                    'repositories' => [
                        ['packagist.org' => false],
                        [
                            'type' => 'path',
                            'url' => $pluginPath,
                            'options' => ['symlink' => true],
                        ],
                    ],
                    'require-dev' => [
                        'yisraeldov/composer-subtree-plugin' => '*',
                    ],
                    'minimum-stability' => 'dev',
                    'prefer-stable' => true,
                    'config' => [
                        'allow-plugins' => [
                            'yisraeldov/composer-subtree-plugin' => true,
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );

        $this->runGitCommand('git add README.md composer.json', $consumerPath);
        $this->runGitCommand("git commit -m 'seed consumer'", $consumerPath);
    }

    private function addUpstreamCommit(
        string $remotePath,
        string $maintainerPath,
    ): void {
        $this->runGitCommand(
            sprintf(
                'git clone %s %s',
                escapeshellarg($remotePath),
                escapeshellarg($maintainerPath),
            ),
            dirname($maintainerPath),
        );
        $this->configureLocalGitIdentity($maintainerPath);

        file_put_contents($maintainerPath . '/upstream.txt', "v2\n");

        $this->runGitCommand('git add upstream.txt', $maintainerPath);
        $this->runGitCommand(
            "git commit -m 'update upstream'",
            $maintainerPath,
        );
        $this->runGitCommand('git push origin main', $maintainerPath);
    }

    private function configureLocalGitIdentity(string $repositoryPath): void
    {
        $this->runGitCommand(
            "git config user.name 'Subtree Test'",
            $repositoryPath,
        );
        $this->runGitCommand(
            "git config user.email 'subtree-test@example.com'",
            $repositoryPath,
        );
    }

    private function runComposerCommand(
        string $command,
        string $workingDirectory,
    ): Process {
        $process = Process::fromShellCommandline($command, $workingDirectory);
        $process->setTimeout(300);
        $process->run();

        if ($process->isSuccessful()) {
            return $process;
        }

        self::fail(
            sprintf(
                "Composer command failed:\n"
                . "command: %s\n"
                . "cwd: %s\n"
                . "stdout: %s\n"
                . "stderr: %s",
                $command,
                $workingDirectory,
                $process->getOutput(),
                $process->getErrorOutput(),
            ),
        );
    }

    private function isComposerAvailable(): bool
    {
        $process = Process::fromShellCommandline('composer --version');
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful();
    }

    private function requireComposerAndSubtreeTools(): void
    {
        if (!$this->isComposerAvailable()) {
            self::markTestSkipped('composer is not available in PATH.');
        }

        if (!$this->isGitSubtreeAvailable()) {
            self::markTestSkipped('git subtree is not available in PATH.');
        }
    }

    private function requireSmokeFlag(
        string $flag,
        string $message,
    ): void {
        if (getenv($flag) === '1') {
            return;
        }

        self::markTestSkipped($message);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function requireGithubPushRemoteAndBranch(): array
    {
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

        return [$remote, $branch];
    }

    private function runGitCommand(
        string $command,
        string $workingDirectory,
    ): void {
        $process = Process::fromShellCommandline($command, $workingDirectory);
        $process->run();

        if ($process->isSuccessful()) {
            return;
        }

        self::fail(
            sprintf(
                "Git command failed:\n"
                . "command: %s\n"
                . "cwd: %s\n"
                . "stdout: %s\n"
                . "stderr: %s",
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
    private function readSubtreesFromComposerManifest(
        string $composerJsonPath,
    ): array {
        $repositories = $this->readRepositoriesFromComposerManifest(
            $composerJsonPath,
        );

        return array_reduce(
            $repositories,
            fn(array $carry, array $repository): array
                => $this->appendSubtreeConfiguration($carry, $repository),
            [],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRepositoriesFromComposerManifest(
        string $composerJsonPath,
    ): array {
        $contents = file_get_contents($composerJsonPath);

        self::assertIsString($contents);

        $decoded = json_decode($contents, true);

        self::assertIsArray($decoded);

        $repositories = $decoded['repositories'] ?? null;

        if (!is_array($repositories)) {
            return [];
        }

        $normalizedRepositories = [];

        foreach ($repositories as $repository) {
            if (!is_array($repository)) {
                continue;
            }

            $normalizedRepositories[]
                = $this->normalizeSubtreeEntry($repository);
        }

        return $normalizedRepositories;
    }

    /**
     * @param array<string, array<string, mixed>> $carry
     * @param array<string, mixed> $repository
     *
     * @return array<string, array<string, mixed>>
     */
    private function appendSubtreeConfiguration(
        array $carry,
        array $repository,
    ): array {
        $subtree = $this->extractSubtreeConfiguration($repository);

        if ($subtree === null) {
            return $carry;
        }

        $carry[$subtree['prefix']] = $subtree;

        return $carry;
    }

    /**
     * @param array<string, mixed> $repository
     *
     * @return array{prefix: string, remote: mixed,
     *   branch: mixed, squash: mixed}|null
     */
    private function extractSubtreeConfiguration(array $repository): ?array
    {
        if (!$this->isPathRepository($repository)) {
            return null;
        }

        $prefix = $this->extractRepositoryPrefix($repository);
        $metadata = $this->extractRepositoryMetadata($repository);

        if ($prefix === null || $metadata === null) {
            return null;
        }

        return $this->normalizeSubtreeMetadata($prefix, $metadata);
    }

    /**
     * @param array<string, mixed> $repository
     */
    private function isPathRepository(array $repository): bool
    {
        return ($repository['type'] ?? null) === 'path';
    }

    /**
     * @param array<string, mixed> $repository
     */
    private function extractRepositoryPrefix(array $repository): ?string
    {
        $prefix = $repository['url'] ?? null;

        return is_string($prefix) ? $prefix : null;
    }

    /**
     * @param array<string, mixed> $repository
     *
     * @return array<mixed>|null
     */
    private function extractRepositoryMetadata(array $repository): ?array
    {
        $metadata = $repository['composer-subtree-plugin'] ?? null;

        return is_array($metadata) ? $metadata : null;
    }

    /**
     * @param array<mixed> $metadata
     *
     * @return array{prefix: string, remote: mixed,
     *   branch: mixed, squash: mixed}
     */
    private function normalizeSubtreeMetadata(
        string $prefix,
        array $metadata,
    ): array {
        return [
            'prefix' => $prefix,
            'remote' => $metadata['remote'] ?? null,
            'branch' => $metadata['branch'] ?? null,
            'squash' => $metadata['squash'] ?? false,
        ];
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

            $this->removeEntry($path);
        }

        rmdir($directory);
    }

    private function removeEntry(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            $this->removeDirectoryRecursively($path);

            return;
        }

        unlink($path);
    }
}
