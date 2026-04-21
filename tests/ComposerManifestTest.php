<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests;

use ComposerSubtreePlugin\Plugin;
use PHPUnit\Framework\TestCase;

final class ComposerManifestTest extends TestCase
{
    public function testItDeclaresComposerPluginType(): void
    {
        $manifest = $this->readComposerManifest();

        self::assertSame('composer-plugin', $manifest['type'] ?? null);
    }

    public function testItDeclaresPluginEntryPointClass(): void
    {
        $manifest = $this->readComposerManifest();
        $extra = $manifest['extra'] ?? null;

        self::assertIsArray($extra);

        self::assertSame(Plugin::class, $extra['class'] ?? null);
    }

    public function testItRequiresComposerPluginApi(): void
    {
        $manifest = $this->readComposerManifest();
        $requirements = $manifest['require'] ?? null;

        self::assertIsArray($requirements);

        self::assertSame('^2.2', $requirements['composer-plugin-api'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerManifest(): array
    {
        $composerJsonPath = dirname(__DIR__) . '/composer.json';
        $composerJson = file_get_contents($composerJsonPath);

        self::assertNotFalse($composerJson);

        $decoded = json_decode($composerJson, true);

        self::assertIsArray($decoded);

        $manifest = [];

        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $manifest[$key] = $value;
        }

        return $manifest;
    }
}
