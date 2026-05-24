<?php

// tests/Unit/Strategies/WarningRenderStrategyTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\WarningRenderStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Records\EmptyRecord;

final class WarningRenderStrategyTest extends UnitTestCase
{
    private WarningRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new WarningRenderStrategy;
    }

    public function test_supports_warning_type(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::WARNING));
        $this->assertFalse($this->strategy->supports(RenderType::SUCCESS));
        $this->assertFalse($this->strategy->supports(RenderType::ERROR));
        $this->assertFalse($this->strategy->supports(RenderType::DEBUG));
    }

    public function test_execute_with_render_record_returns_message_replacement(): void
    {
        $expectedMessage = 'This is a warning message';
        $record = new RenderRecord(type: RenderType::WARNING, message: $expectedMessage);

        $result = $this->strategy->execute($record, RenderType::WARNING);

        $replacements = $result->toAssociativeArray();
        $this->assertArrayHasKey('{{message}}', $replacements);
        $this->assertEquals($expectedMessage, $replacements['{{message}}']);
    }

    public function test_execute_without_render_record_uses_default_message(): void
    {
        $record = new EmptyRecord;

        $result = $this->strategy->execute($record, RenderType::WARNING);

        $replacements = $result->toAssociativeArray();
        $this->assertArrayHasKey('{{message}}', $replacements);
        $this->assertEquals('Warning', $replacements['{{message}}']);
    }

    public function test_execute_with_render_record_without_message_uses_default_message(): void
    {
        $record = new RenderRecord(type: RenderType::WARNING);

        $result = $this->strategy->execute($record, RenderType::WARNING);

        $replacements = $result->toAssociativeArray();
        $this->assertEquals('Warning', $replacements['{{message}}']);
    }

    public function test_execute_always_returns_replacement_collection(): void
    {
        $record = new RenderRecord(type: RenderType::WARNING, message: 'Test');

        $result = $this->strategy->execute($record, RenderType::WARNING);

        $this->assertInstanceOf(ReplacementCollection::class, $result);
    }
}
