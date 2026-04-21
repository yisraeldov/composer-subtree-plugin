<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Command\SubtreePushCommand;
use ComposerSubtreePlugin\Command\SubtreePullCommand;
use ComposerSubtreePlugin\Command\SubtreeCommandProvider;
use PHPUnit\Framework\TestCase;

final class SubtreeCommandProviderTest extends TestCase
{
    public function testItProvidesSubtreeAddPullAndPushCommands(): void
    {
        $composer = $this->createMock(Composer::class);
        $provider = new SubtreeCommandProvider($composer);

        $commands = $provider->getCommands();

        self::assertCount(3, $commands);
        self::assertInstanceOf(SubtreeAddCommand::class, $commands[0]);
        self::assertInstanceOf(SubtreePullCommand::class, $commands[1]);
        self::assertInstanceOf(SubtreePushCommand::class, $commands[2]);
    }
}
