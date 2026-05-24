<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\TableRenderStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class TableRenderStrategyTest extends UnitTestCase
{
    private TableRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new TableRenderStrategy;
    }

    public function test_supports_table_type(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::TABLE));
        $this->assertFalse($this->strategy->supports(RenderType::HELP));
    }

    public function test_execute_returns_replacements(): void
    {
        $headers = new StringTypedCollection;
        $headers->add('Name', 'Email');

        $rows = new RowCollection;
        $row = new RowCollection;
        $row->add('John', 'john@example.com');
        $rows->add($row);

        $record = new DisplayTableRecord($headers, $rows);
        $replacements = $this->strategy->execute($record, RenderType::TABLE);

        $this->assertSame(1, $replacements->count());

        $placeholders = $replacements->getPlaceholders()->toArray();
        $this->assertContains('{{table}}', $placeholders);
    }

    public function test_execute_with_invalid_record_returns_empty(): void
    {
        $record = new RenderRecord(type: RenderType::TABLE);
        $replacements = $this->strategy->execute($record, RenderType::TABLE);

        $this->assertTrue($replacements->isEmpty());
    }
}
