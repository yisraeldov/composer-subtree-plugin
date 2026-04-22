<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use ComposerSubtreePlugin\Command\SubtreeStatusCommand;
use ComposerSubtreePlugin\Git\GitProcessResult;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SubtreeStatusCommandExecuteTest extends TestCase
{
    public function testItShowsConfiguredMapWithOperationalIndicators(): void
    {
        $projectRoot = $this->createProjectRoot();
        $this->writeSubtreeComposerManifest(
            $projectRoot,
            'packages/alpha',
            ['name' => 'acme/alpha'],
        );
        $this->writeSubtreeComposerManifest(
            $projectRoot,
            'packages/zeta',
            ['name' => 'acme/zeta'],
        );

        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::exactly(4))
            ->method('run')
            ->willReturnCallback(
                static function (string $command): GitProcessResult {
                    return match ($command) {
                        "git status --porcelain -- 'packages/alpha'"
                            => new GitProcessResult(0, '', ''),
                        "git ls-remote --exit-code 'https://example.com/alpha.git' 'main'"
                            => new GitProcessResult(0, 'ok', ''),
                        "git status --porcelain -- 'packages/zeta'"
                            => new GitProcessResult(0, "M packages/zeta/src/File.php\n", ''),
                        "git ls-remote --exit-code 'https://example.com/zeta.git' 'main'"
                            => new GitProcessResult(2, '', 'fatal'),
                        default => throw new \RuntimeException(
                            'Unexpected command: ' . $command,
                        ),
                    };
                },
            );

        $tester = $this->createCommandTester(
            $gitRunner,
            [
                [
                    'type' => 'path',
                    'url' => 'packages/zeta',
                    'composer-subtree-plugin' => [
                        'remote' => 'https://example.com/zeta.git',
                        'branch' => 'main',
                    ],
                ],
                [
                    'type' => 'path',
                    'url' => 'packages/alpha',
                    'composer-subtree-plugin' => [
                        'remote' => 'https://example.com/alpha.git',
                        'branch' => 'main',
                    ],
                ],
            ],
            $projectRoot,
        );

        $statusCode = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(0, $statusCode);
        self::assertStringContainsString('name: packages/alpha', $display);
        self::assertStringContainsString('package: acme/alpha', $display);
        self::assertStringContainsString('prefix: packages/alpha', $display);
        self::assertStringContainsString('remote: https://example.com/alpha.git', $display);
        self::assertStringContainsString('branch: main', $display);
        self::assertStringContainsString('dirty: clean', $display);
        self::assertStringContainsString('remote-status: reachable', $display);

        self::assertStringContainsString('name: packages/zeta', $display);
        self::assertStringContainsString('package: acme/zeta', $display);
        self::assertStringContainsString('dirty: dirty', $display);
        self::assertStringContainsString('remote-status: unreachable', $display);

        self::assertLessThan(
            strpos($display, 'name: packages/zeta'),
            strpos($display, 'name: packages/alpha'),
        );
    }

    public function testItUsesUnknownPackageWhenSubtreeComposerManifestIsMissing(): void
    {
        $projectRoot = $this->createProjectRoot();
        $gitRunner = $this->createMock(GitProcessRunner::class);
        $gitRunner->expects(self::exactly(2))
            ->method('run')
            ->willReturnCallback(
                static function (string $command): GitProcessResult {
                    return match ($command) {
                        "git status --porcelain -- 'packages/missing'"
                            => new GitProcessResult(0, '', ''),
                        "git ls-remote --exit-code 'https://example.com/missing.git' 'main'"
                            => new GitProcessResult(0, 'ok', ''),
                        default => throw new \RuntimeException(
                            'Unexpected command: ' . $command,
                        ),
                    };
                },
            );

        $tester = $this->createCommandTester(
            $gitRunner,
            [
                [
                    'type' => 'path',
                    'url' => 'packages/missing',
                    'composer-subtree-plugin' => [
                        'remote' => 'https://example.com/missing.git',
                        'branch' => 'main',
                    ],
                ],
            ],
            $projectRoot,
        );

        $tester->execute([]);

        self::assertStringContainsString('package: unknown', $tester->getDisplay());
    }

    /**
     * @param array<mixed> $repositories
     */
    private function createCommandTester(
        GitProcessRunner $gitRunner,
        array $repositories,
        string $projectRoot,
    ): CommandTester {
        $composer = $this->createMock(Composer::class);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getRepositories')->willReturn($repositories);
        $composer->method('getPackage')->willReturn($package);

        return new CommandTester(
            new SubtreeStatusCommand($composer, $gitRunner, $projectRoot),
        );
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeSubtreeComposerManifest(
        string $projectRoot,
        string $prefix,
        array $manifest,
    ): void {
        $prefixDirectory = $projectRoot . '/' . $prefix;
        mkdir($prefixDirectory, 0777, true);
        file_put_contents(
            $prefixDirectory . '/composer.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT),
        );
    }

    private function createProjectRoot(): string
    {
        $projectRoot = sprintf(
            '%s/composer-subtree-plugin-status-tests-%s',
            sys_get_temp_dir(),
            uniqid('', true),
        );
        mkdir($projectRoot, 0777, true);

        return $projectRoot;
    }
}
