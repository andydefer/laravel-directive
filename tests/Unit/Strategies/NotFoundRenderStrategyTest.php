<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\NotFoundRenderStrategy;
use AndyDefer\Directive\Tests\TestCase;

final class NotFoundRenderStrategyTest extends TestCase
{
    private NotFoundRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new NotFoundRenderStrategy();
    }

    public function test_supports_not_found_type(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::NOT_FOUND));
        $this->assertFalse($this->strategy->supports(RenderType::HELP));
    }

    public function test_execute_with_signature(): void
    {
        $record = new RenderRecord(type: RenderType::NOT_FOUND, signature: 'unknown');
        $replacements = $this->strategy->execute($record, RenderType::NOT_FOUND);

        $values = $replacements->getValues()->toArray();
        $this->assertContains('unknown', $values);
    }

    public function test_execute_without_signature_uses_default(): void
    {
        $record = new RenderRecord(type: RenderType::NOT_FOUND);
        $replacements = $this->strategy->execute($record, RenderType::NOT_FOUND);

        $values = $replacements->getValues()->toArray();
        $this->assertContains('unknown', $values);
    }
}
