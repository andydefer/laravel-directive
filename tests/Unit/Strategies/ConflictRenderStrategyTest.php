<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\ConflictRenderStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class ConflictRenderStrategyTest extends UnitTestCase
{
    private ConflictRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ConflictRenderStrategy;
    }

    public function test_supports_conflict_type(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::CONFLICT));
        $this->assertFalse($this->strategy->supports(RenderType::HELP));
    }

    public function test_execute_returns_replacements(): void
    {
        $classNames = new StringTypedCollection;
        $classNames->add('TestDirective');

        $signatures = new StringTypedCollection;
        $signatures->add('test-cmd');

        $descriptions = new StringTypedCollection;
        $descriptions->add('Test description');

        $record = new ConflictDisplayRecord(
            name: 'test',
            classNames: $classNames,
            signatures: $signatures,
            descriptions: $descriptions,
        );

        $replacements = $this->strategy->execute($record, RenderType::CONFLICT);

        $this->assertSame(2, $replacements->count());

        $placeholders = $replacements->getPlaceholders()->toArray();
        $this->assertContains('{{name}}', $placeholders);
        $this->assertContains('{{options}}', $placeholders);
    }

    public function test_execute_with_invalid_record_returns_empty(): void
    {
        $record = new RenderRecord(type: RenderType::CONFLICT);
        $replacements = $this->strategy->execute($record, RenderType::CONFLICT);

        $this->assertTrue($replacements->isEmpty());
    }
}
