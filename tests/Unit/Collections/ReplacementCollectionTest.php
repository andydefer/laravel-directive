<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Collections;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Records\ReplacementRecord;
use AndyDefer\Directive\Tests\UnitTestCase;

final class ReplacementCollectionTest extends UnitTestCase
{
    public function test_construct_creates_empty_collection(): void
    {
        $collection = new ReplacementCollection;

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
    }

    public function test_add_replacement_adds_record(): void
    {
        $collection = new ReplacementCollection;
        $collection->addReplacement('{{name}}', 'John');

        $this->assertSame(1, $collection->count());

        $first = $collection->firstItem();
        $this->assertInstanceOf(ReplacementRecord::class, $first);
        $this->assertSame('{{name}}', $first->placeholder);
        $this->assertSame('John', $first->value);
    }

    public function test_add_replacement_returns_self_for_chaining(): void
    {
        $collection = new ReplacementCollection;
        $result = $collection->addReplacement('{{name}}', 'John');

        $this->assertSame($collection, $result);
    }

    public function test_add_multiple_replacements(): void
    {
        $collection = new ReplacementCollection;
        $collection
            ->addReplacement('{{name}}', 'John')
            ->addReplacement('{{email}}', 'john@example.com');

        $this->assertSame(2, $collection->count());
    }

    public function test_get_placeholders_returns_string_typed_collection(): void
    {
        $collection = new ReplacementCollection;
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
        $collection = new ReplacementCollection;
        $placeholders = $collection->getPlaceholders();

        $this->assertTrue($placeholders->isEmpty());
    }

    public function test_get_values_returns_string_typed_collection(): void
    {
        $collection = new ReplacementCollection;
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
        $collection = new ReplacementCollection;
        $values = $collection->getValues();

        $this->assertTrue($values->isEmpty());
    }

    public function test_to_associative_array_returns_empty_array_for_empty_collection(): void
    {
        $collection = new ReplacementCollection;
        $result = $collection->toAssociativeArray();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_to_associative_array_converts_single_replacement(): void
    {
        $collection = new ReplacementCollection;
        $collection->addReplacement('{{name}}', 'John');

        $result = $collection->toAssociativeArray();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('{{name}}', $result);
        $this->assertSame('John', $result['{{name}}']);
    }

    public function test_to_associative_array_converts_multiple_replacements(): void
    {
        $collection = new ReplacementCollection;
        $collection
            ->addReplacement('{{name}}', 'John')
            ->addReplacement('{{email}}', 'john@example.com')
            ->addReplacement('{{age}}', '30');

        $result = $collection->toAssociativeArray();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertArrayHasKey('{{name}}', $result);
        $this->assertArrayHasKey('{{email}}', $result);
        $this->assertArrayHasKey('{{age}}', $result);
        $this->assertSame('John', $result['{{name}}']);
        $this->assertSame('john@example.com', $result['{{email}}']);
        $this->assertSame('30', $result['{{age}}']);
    }

    public function test_to_associative_array_preserves_order(): void
    {
        $collection = new ReplacementCollection;
        $collection
            ->addReplacement('{{first}}', 'First')
            ->addReplacement('{{second}}', 'Second')
            ->addReplacement('{{third}}', 'Third');

        $result = $collection->toAssociativeArray();
        $keys = array_keys($result);

        $this->assertSame(['{{first}}', '{{second}}', '{{third}}'], $keys);
        $this->assertSame(['First', 'Second', 'Third'], array_values($result));
    }

    public function test_to_associative_array_works_after_multiple_operations(): void
    {
        $collection = new ReplacementCollection;
        $collection->addReplacement('{{name}}', 'John');
        $collection->addReplacement('{{email}}', 'john@example.com');

        $firstResult = $collection->toAssociativeArray();
        $this->assertCount(2, $firstResult);

        $collection->addReplacement('{{age}}', '30');
        $secondResult = $collection->toAssociativeArray();

        $this->assertCount(3, $secondResult);
        $this->assertArrayHasKey('{{age}}', $secondResult);
        $this->assertSame('30', $secondResult['{{age}}']);
    }
}
