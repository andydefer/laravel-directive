<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\MessageRenderStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;

final class MessageRenderStrategyTest extends UnitTestCase
{
    private MessageRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new MessageRenderStrategy;
    }

    public function test_supports_success_and_error_types(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::SUCCESS));
        $this->assertTrue($this->strategy->supports(RenderType::ERROR));
        $this->assertFalse($this->strategy->supports(RenderType::HELP));
    }

    public function test_execute_with_message(): void
    {
        $record = new RenderRecord(type: RenderType::SUCCESS, message: 'Custom message');
        $replacements = $this->strategy->execute($record, RenderType::SUCCESS);

        $this->assertSame(1, $replacements->count());

        $values = $replacements->getValues()->toArray();
        $this->assertContains('Custom message', $values);
    }

    public function test_execute_without_message_uses_default_success(): void
    {
        $record = new RenderRecord(type: RenderType::SUCCESS);
        $replacements = $this->strategy->execute($record, RenderType::SUCCESS);

        $values = $replacements->getValues()->toArray();
        $this->assertContains('Directive executed successfully', $values);
    }

    public function test_execute_without_message_uses_default_error(): void
    {
        $record = new RenderRecord(type: RenderType::ERROR);
        $replacements = $this->strategy->execute($record, RenderType::ERROR);

        $values = $replacements->getValues()->toArray();
        $this->assertContains('Directive execution failed', $values);
    }
}
