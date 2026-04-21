<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Git;

final class GitProcessRunner
{
    public function run(string $command): GitProcessResult
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $command,
            $descriptorSpec,
            $pipes,
        );

        if (!is_resource($process)) {
            throw new GitProcessException(
                'Failed to start process',
                $command,
                -1,
            );
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new GitProcessResult(
            $exitCode,
            $stdout,
            $stderr,
        );
    }

    public function runOrFail(string $command): GitProcessResult
    {
        $result = $this->run($command);

        if (!$result->isSuccess()) {
            throw new GitProcessException(
                'Git command failed',
                $command,
                $result->exitCode(),
                $result->stderr(),
            );
        }

        return $result;
    }
}
