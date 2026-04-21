<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use ComposerSubtreePlugin\Config\SubtreeConfig;
use Symfony\Component\Console\Input\InputInterface;

final class SubtreeAddInputParser
{
    private const PACKAGES_PREFIX = 'packages/';

    public function parse(InputInterface $input): SubtreeConfig
    {
        $upstreamUrl = $this->readRequiredStringArgument(
            $input,
            'upstream-url',
        );
        $upstreamBranch = $this->readRequiredStringArgument(
            $input,
            'upstream-branch',
        );
        $packageName = $this->extractPackageName($upstreamUrl);

        return new SubtreeConfig(
            name: $packageName,
            package: $packageName,
            prefix: $this->normalizePrefix(
                $input->getArgument('prefix'),
                $upstreamUrl,
            ),
            remote: $upstreamUrl,
            branch: $upstreamBranch,
            squash: (bool) $input->getOption('squash'),
        );
    }

    private function readRequiredStringArgument(
        InputInterface $input,
        string $argument,
    ): string {
        $value = $input->getArgument($argument);

        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException(
                sprintf('Argument %s must be a non-empty string', $argument),
            );
        }

        return $value;
    }

    private function normalizePrefix(mixed $prefix, string $upstreamUrl): string
    {
        if (is_string($prefix)) {
            return $prefix;
        }

        return $this->defaultPrefixFromUrl($upstreamUrl);
    }

    private function defaultPrefixFromUrl(string $url): string
    {
        $basename = basename($this->extractPackageName($url));

        return self::PACKAGES_PREFIX . $basename;
    }

    private function extractPackageName(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            return $this->trimGitSuffix(trim($path, '/'));
        }

        $colonPosition = strrpos($url, ':');

        if ($colonPosition === false) {
            return $this->trimGitSuffix(basename($url));
        }

        $suffix = substr($url, $colonPosition + 1);

        return $this->trimGitSuffix(trim($suffix, '/'));
    }

    private function trimGitSuffix(string $path): string
    {
        if (!str_ends_with($path, '.git')) {
            return $path;
        }

        return substr($path, 0, -4);
    }
}
