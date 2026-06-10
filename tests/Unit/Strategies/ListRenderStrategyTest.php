<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\ListRenderStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class ListRenderStrategyTest extends UnitTestCase
{
    private ListRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ListRenderStrategy;
    }

    public function test_supports_list_and_empty_types(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::LIST));
        $this->assertTrue($this->strategy->supports(RenderType::EMPTY));
        $this->assertFalse($this->strategy->supports(RenderType::HELP));
    }

    public function test_execute_with_directives_returns_replacements(): void
    {
        $directives = new DirectiveMetadataCollection;

        $aliases = new StringTypedCollection;
        $directive = new DirectiveMetadataRecord(
            signature: 'test-cmd',
            class: \stdClass::class,
            description: 'Test command',
            aliases: $aliases,
        );
        $directives->add($directive);

        $record = new RenderRecord(type: RenderType::LIST, directives: $directives);
        $replacements = $this->strategy->execute($record, RenderType::LIST);

        $this->assertSame(2, $replacements->count());

        $placeholders = $replacements->getPlaceholders()->toArray();
        $this->assertContains('{{count}}', $placeholders);
        $this->assertContains('{{rows}}', $placeholders);
    }

    public function test_execute_without_directives_returns_empty(): void
    {
        $record = new RenderRecord(type: RenderType::LIST);
        $replacements = $this->strategy->execute($record, RenderType::LIST);

        $this->assertTrue($replacements->isEmpty());
    }

    public function test_execute_with_empty_type_returns_empty(): void
    {
        $record = new RenderRecord(type: RenderType::EMPTY);
        $replacements = $this->strategy->execute($record, RenderType::EMPTY);

        $this->assertTrue($replacements->isEmpty());
    }
}
