<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use ComposerSubtreePlugin\Command\SubtreePullCommand;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;

final class SubtreePullCommandTest extends TestCase
{
    public function testItHasCorrectName(): void
    {
        $command = new SubtreePullCommand(
            $this->createMock(Composer::class),
            $this->createMock(GitProcessRunner::class),
        );

        self::assertSame('subtree:pull', $command->getName());
    }

    public function testItHasOptionalTargetArgument(): void
    {
        $command = new SubtreePullCommand(
            $this->createMock(Composer::class),
            $this->createMock(GitProcessRunner::class),
        );
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasArgument('target'));
        self::assertFalse($definition->getArgument('target')->isRequired());
    }
}
