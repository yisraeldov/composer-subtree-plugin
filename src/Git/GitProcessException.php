<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Git;

use RuntimeException;

final class GitProcessException extends RuntimeException
{
    private readonly string $command;

    public function __construct(string $message, string $command, int $exitCode, ?string $stderr = null)
    {
        $fullMessage = $message;
        $fullMessage .= ' (command: ' . $command . ', exit code: ' . $exitCode . ')';

        if ($stderr !== null && $stderr !== '') {
            $fullMessage .= PHP_EOL . 'stderr: ' . $stderr;
        }

        parent::__construct($fullMessage, $exitCode);

        $this->command = $command;
    }

    public function getCommand(): string
    {
        return $this->command;
    }
}
