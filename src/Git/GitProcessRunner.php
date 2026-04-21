<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Git;

use Symfony\Component\Process\Process;

class GitProcessRunner
{
    public function run(string $command): GitProcessResult
    {
        $process = Process::fromShellCommandline($command);
        $process->run();

        $exitCode = $process->getExitCode() ?? 0;

        return new GitProcessResult(
            $exitCode,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }

    public function runOrFail(string $command): GitProcessResult
    {
        $process = Process::fromShellCommandline($command);
        $process->run();

        if (!$process->isSuccessful()) {
            $exitCode = $process->getExitCode() ?? 1;

            throw new GitProcessException(
                'Git command failed',
                $command,
                $exitCode,
                $process->getErrorOutput(),
            );
        }

        $exitCode = $process->getExitCode() ?? 0;

        return new GitProcessResult(
            $exitCode,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }
}