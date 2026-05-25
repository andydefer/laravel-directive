<?php

// tests/Unit/Strategies/VersionRenderStrategyTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\VersionRenderStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;

final class VersionRenderStrategyTest extends UnitTestCase
{
    private VersionRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new VersionRenderStrategy;
    }

    public function test_supports_version_type(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::VERSION));
        $this->assertFalse($this->strategy->supports(RenderType::HELP));
        $this->assertFalse($this->strategy->supports(RenderType::SUCCESS));
        $this->assertFalse($this->strategy->supports(RenderType::ERROR));
    }

    public function test_execute_returns_replacements(): void
    {
        $record = new RenderRecord(type: RenderType::VERSION);
        $result = $this->strategy->execute($record, RenderType::VERSION);

        $this->assertInstanceOf(ReplacementCollection::class, $result);

        $replacements = $result->toAssociativeArray();

        $this->assertArrayHasKey('{{version}}', $replacements);
        $this->assertArrayHasKey('{{php_version}}', $replacements);
        $this->assertArrayHasKey('{{laravel_version}}', $replacements);

        $this->assertEquals(PHP_VERSION, $replacements['{{php_version}}']);
    }

    public function test_version_placeholder_contains_valid_version(): void
    {
        $record = new RenderRecord(type: RenderType::VERSION);
        $result = $this->strategy->execute($record, RenderType::VERSION);

        $replacements = $result->toAssociativeArray();

        // La version peut être 'unknown' ou un numéro de version
        $this->assertIsString($replacements['{{version}}']);
    }

    public function test_php_version_placeholder_contains_current_php_version(): void
    {
        $record = new RenderRecord(type: RenderType::VERSION);
        $result = $this->strategy->execute($record, RenderType::VERSION);

        $replacements = $result->toAssociativeArray();

        $this->assertEquals(PHP_VERSION, $replacements['{{php_version}}']);
    }

    public function test_laravel_version_placeholder_contains_version_string(): void
    {
        $record = new RenderRecord(type: RenderType::VERSION);
        $result = $this->strategy->execute($record, RenderType::VERSION);

        $replacements = $result->toAssociativeArray();

        // La version de Laravel peut être 'unknown' ou un numéro de version comme '^13.0'
        $this->assertIsString($replacements['{{laravel_version}}']);
    }

    public function test_all_placeholders_are_strings(): void
    {
        $record = new RenderRecord(type: RenderType::VERSION);
        $result = $this->strategy->execute($record, RenderType::VERSION);

        $replacements = $result->toAssociativeArray();

        foreach ($replacements as $placeholder => $value) {
            $this->assertIsString($value, "Placeholder {$placeholder} should be a string");
        }
    }
}
