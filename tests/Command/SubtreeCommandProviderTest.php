<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Command\SubtreePullCommand;
use ComposerSubtreePlugin\Command\SubtreeCommandProvider;
use ComposerSubtreePlugin\Command\SubtreePushCommand;
use ComposerSubtreePlugin\Command\SubtreeStatusCommand;
use PHPUnit\Framework\TestCase;

final class SubtreeCommandProviderTest extends TestCase
{
    public function testItProvidesSubtreeAddPullPushAndStatusCommands(): void
    {
        $composer = $this->createMock(Composer::class);
        $provider = new SubtreeCommandProvider([
            'composer' => $composer,
        ]);

        $commands = $provider->getCommands();

        self::assertCount(4, $commands);
        self::assertInstanceOf(SubtreeAddCommand::class, $commands[0]);
        self::assertInstanceOf(SubtreePullCommand::class, $commands[1]);
        self::assertInstanceOf(SubtreePushCommand::class, $commands[2]);
        self::assertInstanceOf(SubtreeStatusCommand::class, $commands[3]);
    }

    public function testItRejectsMissingComposerCapabilityArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Command provider requires a Composer instance in capability args.',
        );

        new SubtreeCommandProvider([]);
    }
}
