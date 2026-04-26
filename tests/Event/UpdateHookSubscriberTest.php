<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Event;

use Composer\Package\RootPackageInterface;
use Composer\Plugin\PreCommandRunEvent;
use ComposerSubtreePlugin\Event\UpdateHookSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;

final class UpdateHookSubscriberTest extends TestCase
{
    public function testItDetectsComposerUpdateCommand(): void
    {
        $subscriber = new UpdateHookSubscriber();
        $event = $this->createPreCommandRunEvent('update');

        self::assertTrue($subscriber->shouldRunFor($event));
    }

    public function testItIgnoresNonUpdateCommands(): void
    {
        $subscriber = new UpdateHookSubscriber();
        $event = $this->createPreCommandRunEvent('install');

        self::assertFalse($subscriber->shouldRunFor($event));
    }

    public function testItReturnsNoTargetedSubtreesForBareUpdate(): void
    {
        $subscriber = new UpdateHookSubscriber();
        $event = $this->createPreCommandRunEvent('update', []);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getRepositories')->willReturn([
            [
                'type' => 'path',
                'url' => 'packages/pcre',
                'composer-subtree-plugin' => [
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                ],
            ],
        ]);

        self::assertSame([], $subscriber->targetedSubtrees($event, $package));
    }

    public function testItMatchesRequestedPackageToSubtreePrefix(): void
    {
        $projectRoot = $this->createProjectRoot();
        $this->writeComposerManifest(
            $projectRoot,
            'packages/pcre',
            ['name' => 'composer/pcre'],
        );

        $subscriber = new UpdateHookSubscriber(projectRoot: $projectRoot);
        $event = $this->createPreCommandRunEvent('update', ['composer/pcre']);
        $package = $this->createMock(RootPackageInterface::class);
        $package->method('getRepositories')->willReturn([
            [
                'type' => 'path',
                'url' => 'packages/pcre',
                'composer-subtree-plugin' => [
                    'remote' => 'https://github.com/composer/pcre.git',
                    'branch' => 'main',
                ],
            ],
            [
                'type' => 'path',
                'url' => 'packages/other',
                'composer-subtree-plugin' => [
                    'remote' => 'https://example.com/other.git',
                    'branch' => 'main',
                ],
            ],
        ]);

        $targetedSubtrees = $subscriber->targetedSubtrees($event, $package);

        self::assertSame(['packages/pcre'], array_keys($targetedSubtrees));
    }

    /**
     * @param array<int, string> $packages
     */
    private function createPreCommandRunEvent(
        string $command,
        array $packages = [],
    ): PreCommandRunEvent
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getArgument')
            ->willReturnCallback(
                static fn(string $name): mixed => $name === 'packages'
                    ? $packages
                    : null,
            );

        return new PreCommandRunEvent(
            'pre-command-run',
            $input,
            $command,
        );
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeComposerManifest(
        string $projectRoot,
        string $prefix,
        array $manifest,
    ): void {
        $directory = $projectRoot . '/' . $prefix;
        mkdir($directory, 0777, true);
        file_put_contents(
            $directory . '/composer.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT),
        );
    }

    private function createProjectRoot(): string
    {
        $projectRoot = sprintf(
            '%s/composer-subtree-plugin-update-tests-%s',
            sys_get_temp_dir(),
            uniqid('', true),
        );
        mkdir($projectRoot, 0777, true);

        return $projectRoot;
    }
}
