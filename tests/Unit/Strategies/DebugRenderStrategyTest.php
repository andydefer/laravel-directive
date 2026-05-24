<?php

// tests/Unit/Strategies/DebugRenderStrategyTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\DebugRenderStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Records\EmptyRecord;

final class DebugRenderStrategyTest extends UnitTestCase
{
    private DebugRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new DebugRenderStrategy;
    }

    public function test_supports_debug_type(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::DEBUG));
        $this->assertFalse($this->strategy->supports(RenderType::SUCCESS));
        $this->assertFalse($this->strategy->supports(RenderType::ERROR));
        $this->assertFalse($this->strategy->supports(RenderType::WARNING));
    }

    public function test_execute_with_render_record_returns_message_replacement(): void
    {
        $expectedMessage = 'Debug information here';
        $record = new RenderRecord(type: RenderType::DEBUG, message: $expectedMessage);

        $result = $this->strategy->execute($record, RenderType::DEBUG);

        $replacements = $result->toAssociativeArray();
        $this->assertArrayHasKey('{{message}}', $replacements);
        $this->assertEquals($expectedMessage, $replacements['{{message}}']);
    }

    public function test_execute_without_render_record_uses_default_debug_message(): void
    {
        $record = new EmptyRecord;

        $result = $this->strategy->execute($record, RenderType::DEBUG);

        $replacements = $result->toAssociativeArray();
        $this->assertArrayHasKey('{{message}}', $replacements);
        $this->assertEquals('Debug message', $replacements['{{message}}']);
    }

    public function test_execute_with_render_record_without_message_uses_default_debug_message(): void
    {
        $record = new RenderRecord(type: RenderType::DEBUG);

        $result = $this->strategy->execute($record, RenderType::DEBUG);

        $replacements = $result->toAssociativeArray();
        $this->assertEquals('Debug message', $replacements['{{message}}']);
    }

    public function test_execute_always_returns_replacement_collection(): void
    {
        $record = new RenderRecord(type: RenderType::DEBUG, message: 'Test debug');

        $result = $this->strategy->execute($record, RenderType::DEBUG);

        $this->assertInstanceOf(ReplacementCollection::class, $result);
    }

    public function test_debug_message_can_be_empty_string(): void
    {
        $record = new RenderRecord(type: RenderType::DEBUG, message: '');

        $result = $this->strategy->execute($record, RenderType::DEBUG);

        $replacements = $result->toAssociativeArray();
        $this->assertEquals('', $replacements['{{message}}']);
    }

    public function test_debug_message_can_be_long_string(): void
    {
        $longMessage = str_repeat('Debug ', 100);
        $record = new RenderRecord(type: RenderType::DEBUG, message: $longMessage);

        $result = $this->strategy->execute($record, RenderType::DEBUG);

        $replacements = $result->toAssociativeArray();
        $this->assertEquals($longMessage, $replacements['{{message}}']);
    }
}
