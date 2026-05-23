<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Collections;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Records\ReplacementRecord;
use AndyDefer\Directive\Tests\TestCase;

final class ReplacementCollectionTest extends TestCase
{
    public function test_construct_creates_empty_collection(): void
    {
        $collection = new ReplacementCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
    }

    public function test_add_replacement_adds_record(): void
    {
        $collection = new ReplacementCollection();
        $collection->addReplacement('{{name}}', 'John');

        $this->assertSame(1, $collection->count());

        $first = $collection->firstItem();
        $this->assertInstanceOf(ReplacementRecord::class, $first);
        $this->assertSame('{{name}}', $first->placeholder);
        $this->assertSame('John', $first->value);
    }

    public function test_add_replacement_returns_self_for_chaining(): void
    {
        $collection = new ReplacementCollection();
        $result = $collection->addReplacement('{{name}}', 'John');

        $this->assertSame($collection, $result);
    }

    public function test_add_multiple_replacements(): void
    {
        $collection = new ReplacementCollection();
        $collection
            ->addReplacement('{{name}}', 'John')
            ->addReplacement('{{email}}', 'john@example.com');

        $this->assertSame(2, $collection->count());
    }

    public function test_get_placeholders_returns_string_typed_collection(): void
    {
        $collection = new ReplacementCollection();
        $collection
            ->addReplacement('{{name}}', 'John')
            ->addReplacement('{{email}}', 'john@example.com');

        $placeholders = $collection->getPlaceholders();

        $this->assertSame(2, $placeholders->count());
        $this->assertTrue($placeholders->contains('{{name}}'));
        $this->assertTrue($placeholders->contains('{{email}}'));
    }

    public function test_get_placeholders_returns_empty_when_no_replacements(): void
    {
        $collection = new ReplacementCollection();
        $placeholders = $collection->getPlaceholders();

        $this->assertTrue($placeholders->isEmpty());
    }

    public function test_get_values_returns_string_typed_collection(): void
    {
        $collection = new ReplacementCollection();
        $collection
            ->addReplacement('{{name}}', 'John')
            ->addReplacement('{{email}}', 'john@example.com');

        $values = $collection->getValues();

        $this->assertSame(2, $values->count());
        $this->assertTrue($values->contains('John'));
        $this->assertTrue($values->contains('john@example.com'));
    }

    public function test_get_values_returns_empty_when_no_replacements(): void
    {
        $collection = new ReplacementCollection();
        $values = $collection->getValues();

        $this->assertTrue($values->isEmpty());
    }
}
