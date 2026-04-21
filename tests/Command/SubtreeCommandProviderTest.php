<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use ComposerSubtreePlugin\Command\SubtreeCommandProvider;
use PHPUnit\Framework\TestCase;

final class SubtreeCommandProviderTest extends TestCase
{
    public function testItProvidesNoCommandsYet(): void
    {
        $provider = new SubtreeCommandProvider();

        self::assertSame([], $provider->getCommands());
    }
}
