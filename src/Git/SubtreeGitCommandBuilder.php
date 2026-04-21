<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Git;

use ComposerSubtreePlugin\Config\SubtreeConfig;

final class SubtreeGitCommandBuilder
{
    public function add(SubtreeConfig $config): string
    {
        $command = sprintf(
            'git subtree add --prefix=%s %s %s',
            escapeshellarg($config->prefix()),
            escapeshellarg($config->remote()),
            escapeshellarg($config->branch()),
        );

        if (!$config->squash()) {
            return $command;
        }

        return $command . ' --squash';
    }

    public function fetch(SubtreeConfig $config): string
    {
        return sprintf(
            'git fetch %s %s',
            escapeshellarg($config->remote()),
            escapeshellarg($config->branch()),
        );
    }

    public function pull(SubtreeConfig $config): string
    {
        $command = sprintf(
            'git subtree pull --prefix=%s %s %s',
            escapeshellarg($config->prefix()),
            escapeshellarg($config->remote()),
            escapeshellarg($config->branch()),
        );

        if (!$config->squash()) {
            return $command;
        }

        return $command . ' --squash';
    }

    public function push(SubtreeConfig $config): string
    {
        return sprintf(
            'git subtree push --prefix=%s %s %s',
            escapeshellarg($config->prefix()),
            escapeshellarg($config->remote()),
            escapeshellarg($config->branch()),
        );
    }
}
