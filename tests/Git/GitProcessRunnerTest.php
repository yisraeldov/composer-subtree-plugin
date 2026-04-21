<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Git;

use ComposerSubtreePlugin\Git\GitProcessRunner;
use ComposerSubtreePlugin\Git\GitProcessException;
use PHPUnit\Framework\TestCase;

final class GitProcessRunnerTest extends TestCase
{
    public function testItRunsCommandAndCapturesOutput(): void
    {
        $runner = new GitProcessRunner();
        $result = $runner->run('echo hello');

        self::assertSame(0, $result->exitCode());
        self::assertStringContainsString('hello', $result->stdout());
    }

    public function testItCapturesExitCodeOnFailure(): void
    {
        $runner = new GitProcessRunner();
        $result = $runner->run('false');

        self::assertNotSame(0, $result->exitCode());
    }

    public function testItThrowsExceptionOnNonZeroExit(): void
    {
        $runner = new GitProcessRunner();

        $this->expectException(GitProcessException::class);
        $this->expectExceptionMessage('not-a-real-command-that-fails');
        $this->expectExceptionMessage('exit code');

        $runner->runOrFail('git not-a-real-command-that-fails');
    }

    public function testItCapturesStderrInException(): void
    {
        $runner = new GitProcessRunner();

        try {
            $runner->runOrFail('git status --not-a-valid-option');
        } catch (GitProcessException $e) {
            self::assertStringContainsString('unknown option', $e->getMessage());
            self::assertSame('git status --not-a-valid-option', $e->getCommand());

            return;
        }

        self::fail('Expected GitProcessException was not thrown');
    }
}
