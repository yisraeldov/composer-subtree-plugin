<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Command\SubtreePullCommand;
use ComposerSubtreePlugin\Command\SubtreeCommandProvider;
use PHPUnit\Framework\TestCase;

final class SubtreeCommandProviderTest extends TestCase
{
    public function testItProvidesSubtreeAddAndPullCommands(): void
    {
        $composer = $this->createMock(Composer::class);
        $provider = new SubtreeCommandProvider($composer);

        $commands = $provider->getCommands();

        self::assertCount(2, $commands);
        self::assertInstanceOf(SubtreeAddCommand::class, $commands[0]);
        self::assertInstanceOf(SubtreePullCommand::class, $commands[1]);
    }
}
