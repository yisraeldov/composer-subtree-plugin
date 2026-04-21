<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests;

use ComposerSubtreePlugin\PluginName;
use PHPUnit\Framework\TestCase;

final class PluginNameTest extends TestCase
{
    public function testItReturnsThePackageName(): void
    {
        self::assertSame('composer-subtree-plugin', PluginName::value());
    }
}
