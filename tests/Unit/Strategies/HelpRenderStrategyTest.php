<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\HelpRenderStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;

final class HelpRenderStrategyTest extends UnitTestCase
{
    private HelpRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new HelpRenderStrategy;
    }

    public function test_supports_help_type(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::HELP));
        $this->assertFalse($this->strategy->supports(RenderType::LIST));
        $this->assertFalse($this->strategy->supports(RenderType::ERROR));
    }

    public function test_execute_returns_empty_replacements(): void
    {
        $record = new RenderRecord(type: RenderType::HELP);
        $replacements = $this->strategy->execute($record, RenderType::HELP);

        $this->assertTrue($replacements->isEmpty());
    }
}
