<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Collections;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Records\Collections\TypedCollection;
use PHPUnit\Framework\TestCase;

final class ParameterCollectionTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function test_constructor_creates_empty_collection(): void
    {
        $collection = new ParameterCollection();

        $this->assertInstanceOf(ParameterCollection::class, $collection);
        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->count());
    }

    public function test_constructor_allows_only_parameter_records(): void
    {
        $collection = new ParameterCollection();
        $record = new ParameterRecord(name: 'test', value: 'value');

        $collection->add($record);

        $this->assertSame(1, $collection->count());
        $this->assertSame($record, $collection->firstItem());
    }

    public function test_can_add_multiple_records(): void
    {
        $collection = new ParameterCollection();
        $record1 = new ParameterRecord(name: 'name', value: 'John');
        $record2 = new ParameterRecord(name: 'age', value: '30');

        $collection->add($record1, $record2);

        $this->assertSame(2, $collection->count());
    }

    // ==================== fromFlatArguments Tests ====================

    public function test_from_flat_arguments_converts_flat_format(): void
    {
        $flat = new TypedCollection('string');
        $flat->add('John', 'name', '30', 'age', 'admin', 'role');

        $result = ParameterCollection::fromFlatArguments($flat);

        $this->assertSame(3, $result->count());
        $this->assertSame('John', $result->get('name'));
        $this->assertSame('30', $result->get('age'));
        $this->assertSame('admin', $result->get('role'));
    }

    public function test_from_flat_arguments_handles_empty_collection(): void
    {
        $flat = new TypedCollection('string');

        $result = ParameterCollection::fromFlatArguments($flat);

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->count());
    }

    public function test_from_flat_arguments_skips_incomplete_pair(): void
    {
        $flat = new TypedCollection('string');
        $flat->add('John', 'name', '30');

        $result = ParameterCollection::fromFlatArguments($flat);

        $this->assertSame(1, $result->count());
        $this->assertSame('John', $result->get('name'));
    }

    public function test_from_flat_arguments_handles_null_values(): void
    {
        $flat = new TypedCollection('string', 'null');
        $flat->add(null, 'nullable', 'value', 'name');

        $result = ParameterCollection::fromFlatArguments($flat);

        $this->assertSame(1, $result->count());
        $this->assertSame('value', $result->get('name'));
    }

    public function test_from_flat_arguments_handles_mixed_types(): void
    {
        $flat = new TypedCollection('string', 'int', 'null');
        $flat->add('John', 'name', 30, 'age', null, 'optional');

        $result = ParameterCollection::fromFlatArguments($flat);

        $this->assertSame(2, $result->count());
        $this->assertSame('John', $result->get('name'));
        $this->assertSame(30, $result->get('age'));
    }

    // ==================== fromFlatOptions Tests ====================

    public function test_from_flat_options_converts_flat_format(): void
    {
        $flat = new TypedCollection('string');
        $flat->add('name', 'John', 'active', 'true', 'count', '5');

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertSame(3, $result->count());
        $this->assertSame('John', $result->get('name'));
        $this->assertTrue($result->get('active'));
        $this->assertSame('5', $result->get('count'));
    }

    public function test_from_flat_options_converts_true_string_to_boolean(): void
    {
        $flat = new TypedCollection('string');
        $flat->add('active', 'true', 'enabled', 'true', 'verbose', 'true');

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertTrue($result->get('active'));
        $this->assertTrue($result->get('enabled'));
        $this->assertTrue($result->get('verbose'));
    }

    public function test_from_flat_options_converts_false_string_to_boolean(): void
    {
        $flat = new TypedCollection('string');
        $flat->add('active', 'false', 'enabled', 'false', 'debug', 'false');

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertFalse($result->get('active'));
        $this->assertFalse($result->get('enabled'));
        $this->assertFalse($result->get('debug'));
    }

    public function test_from_flat_options_converts_null_to_true(): void
    {
        $flat = new TypedCollection('string', 'null');
        $flat->add('verbose', null, 'quiet', null, 'force', null);

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertTrue($result->get('verbose'));
        $this->assertTrue($result->get('quiet'));
        $this->assertTrue($result->get('force'));
    }

    /**
     * Test that empty string values are converted to true (flag option without value).
     */
    public function test_from_flat_options_converts_empty_string_to_true(): void
    {
        $flat = new TypedCollection('string');
        $flat->add('verbose', '', 'quiet', '', 'force', '');

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertTrue($result->get('verbose'));
        $this->assertTrue($result->get('quiet'));
        $this->assertTrue($result->get('force'));
    }

    public function test_from_flat_options_preserves_string_values(): void
    {
        $flat = new TypedCollection('string');
        $flat->add('name', 'John Doe', 'email', 'john@example.com', 'role', 'admin');

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertSame('John Doe', $result->get('name'));
        $this->assertSame('john@example.com', $result->get('email'));
        $this->assertSame('admin', $result->get('role'));
    }

    public function test_from_flat_options_handles_empty_collection(): void
    {
        $flat = new TypedCollection('string');

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->count());
    }

    public function test_from_flat_options_skips_entry_without_name(): void
    {
        $flat = new TypedCollection('string', 'null');
        $flat->add(null, 'value', 'name', 'John');

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertSame(1, $result->count());
        $this->assertSame('John', $result->get('name'));
    }

    public function test_from_flat_options_handles_mixed_boolean_and_string(): void
    {
        $flat = new TypedCollection('string', 'null');
        $flat->add('active', 'true', 'name', 'John', 'verbose', null, 'count', '10');

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertTrue($result->get('active'));
        $this->assertSame('John', $result->get('name'));
        $this->assertTrue($result->get('verbose'));
        $this->assertSame('10', $result->get('count'));
    }

    /**
     * Test mixed conversions including empty string.
     */
    public function test_from_flat_options_converts_mixed_values_correctly(): void
    {
        $flat = new TypedCollection('string', 'null');
        $flat->add(
            'string_value',
            'hello',
            'true_value',
            'true',
            'false_value',
            'false',
            'null_value',
            null,
            'empty_value',
            '',
            'numeric_value',
            '42'
        );

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertSame('hello', $result->get('string_value'));
        $this->assertTrue($result->get('true_value'));
        $this->assertFalse($result->get('false_value'));
        $this->assertTrue($result->get('null_value'));
        $this->assertTrue($result->get('empty_value'));
        $this->assertSame('42', $result->get('numeric_value'));
    }

    // ==================== toAssociativeArray Tests ====================

    public function test_to_associative_array_converts_collection(): void
    {
        $collection = new ParameterCollection();
        $collection->add(
            new ParameterRecord(name: 'name', value: 'John'),
            new ParameterRecord(name: 'age', value: 30),
            new ParameterRecord(name: 'active', value: true)
        );

        $result = $collection->toAssociativeArray();

        $this->assertSame([
            'name' => 'John',
            'age' => 30,
            'active' => true,
        ], $result);
    }

    public function test_to_associative_array_returns_empty_array_for_empty_collection(): void
    {
        $collection = new ParameterCollection();

        $result = $collection->toAssociativeArray();

        $this->assertSame([], $result);
    }

    public function test_to_associative_array_preserves_null_values(): void
    {
        $collection = new ParameterCollection();
        $collection->add(new ParameterRecord(name: 'optional', value: null));

        $result = $collection->toAssociativeArray();

        $this->assertNull($result['optional']);
    }

    // ==================== get Method Tests ====================

    public function test_get_returns_value_by_name(): void
    {
        $collection = new ParameterCollection();
        $collection->add(
            new ParameterRecord(name: 'name', value: 'John'),
            new ParameterRecord(name: 'age', value: 30)
        );

        $this->assertSame('John', $collection->get('name'));
        $this->assertSame(30, $collection->get('age'));
    }

    public function test_get_returns_null_for_non_existent_name(): void
    {
        $collection = new ParameterCollection();
        $collection->add(new ParameterRecord(name: 'name', value: 'John'));

        $this->assertNull($collection->get('unknown'));
    }

    public function test_get_returns_null_when_collection_empty(): void
    {
        $collection = new ParameterCollection();

        $this->assertNull($collection->get('anything'));
    }

    public function test_get_returns_boolean_value(): void
    {
        $collection = new ParameterCollection();
        $collection->add(
            new ParameterRecord(name: 'active', value: true),
            new ParameterRecord(name: 'enabled', value: false)
        );

        $this->assertTrue($collection->get('active'));
        $this->assertFalse($collection->get('enabled'));
    }

    // ==================== has Method Tests ====================

    public function test_has_returns_true_when_parameter_exists(): void
    {
        $collection = new ParameterCollection();
        $collection->add(
            new ParameterRecord(name: 'name', value: 'John'),
            new ParameterRecord(name: 'age', value: 30)
        );

        $this->assertTrue($collection->has('name'));
        $this->assertTrue($collection->has('age'));
    }

    public function test_has_returns_false_when_parameter_does_not_exist(): void
    {
        $collection = new ParameterCollection();
        $collection->add(new ParameterRecord(name: 'name', value: 'John'));

        $this->assertFalse($collection->has('unknown'));
    }

    public function test_has_returns_false_when_collection_empty(): void
    {
        $collection = new ParameterCollection();

        $this->assertFalse($collection->has('anything'));
    }

    public function test_has_distinguishes_between_similar_names(): void
    {
        $collection = new ParameterCollection();
        $collection->add(
            new ParameterRecord(name: 'name', value: 'John'),
            new ParameterRecord(name: 'full_name', value: 'John Doe')
        );

        $this->assertTrue($collection->has('name'));
        $this->assertTrue($collection->has('full_name'));
        $this->assertFalse($collection->has('full'));
    }

    // ==================== Edge Cases Tests ====================

    public function test_handles_large_collections(): void
    {
        $collection = new ParameterCollection();

        for ($i = 0; $i < 1000; $i++) {
            $collection->add(new ParameterRecord(name: "key_{$i}", value: "value_{$i}"));
        }

        $this->assertSame(1000, $collection->count());
        $this->assertSame('value_500', $collection->get('key_500'));
        $this->assertTrue($collection->has('key_999'));
    }

    public function test_from_flat_arguments_handles_large_flat_collection(): void
    {
        $flat = new TypedCollection('string');

        for ($i = 0; $i < 500; $i++) {
            $flat->add("value_{$i}", "key_{$i}");
        }

        $result = ParameterCollection::fromFlatArguments($flat);

        $this->assertSame(500, $result->count());
        $this->assertSame('value_250', $result->get('key_250'));
    }

    public function test_from_flat_options_handles_large_flat_collection(): void
    {
        $flat = new TypedCollection('string');

        for ($i = 0; $i < 500; $i++) {
            $flat->add("key_{$i}", "value_{$i}");
        }

        $result = ParameterCollection::fromFlatOptions($flat);

        $this->assertSame(500, $result->count());
        $this->assertSame('value_250', $result->get('key_250'));
    }
}
