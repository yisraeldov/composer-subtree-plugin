<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;

final class SubtreeAddCommandTest extends TestCase
{
    private Composer $composer;
    private GitProcessRunner $gitRunner;

    protected function setUp(): void
    {
        $this->composer = $this->createMock(Composer::class);
        $this->gitRunner = $this->createMock(GitProcessRunner::class);
    }

    public function testItHasCorrectName(): void
    {
        $command = new SubtreeAddCommand($this->composer, $this->gitRunner);

        self::assertSame('subtree:add', $command->getName());
    }

    public function testItRequiresUpstreamUrlArgument(): void
    {
        $command = new SubtreeAddCommand($this->composer, $this->gitRunner);
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasArgument('upstream-url'));
        $argument = $definition->getArgument('upstream-url');

        self::assertTrue($argument->isRequired());
    }

    public function testItRequiresUpstreamBranchArgument(): void
    {
        $command = new SubtreeAddCommand($this->composer, $this->gitRunner);
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasArgument('upstream-branch'));
        $argument = $definition->getArgument('upstream-branch');

        self::assertTrue($argument->isRequired());
    }

    public function testItHasOptionalPrefixArgument(): void
    {
        $command = new SubtreeAddCommand($this->composer, $this->gitRunner);
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasArgument('prefix'));
        $argument = $definition->getArgument('prefix');

        self::assertFalse($argument->isRequired());
    }

    public function testItHasSquashOption(): void
    {
        $command = new SubtreeAddCommand($this->composer, $this->gitRunner);
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasOption('squash'));
    }

    public function testItProvidesActionableHelpExamples(): void
    {
        $command = new SubtreeAddCommand($this->composer, $this->gitRunner);

        self::assertStringContainsString(
            'composer subtree:add',
            $command->getHelp(),
        );
    }

}
