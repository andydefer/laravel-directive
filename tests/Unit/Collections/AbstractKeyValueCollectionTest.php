<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Collections;

use AndyDefer\Directive\Collections\AbstractKeyValueCollection;
use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Tests\UnitTestCase;

final class AbstractKeyValueCollectionTest extends UnitTestCase
{
    public function test_firstItem_returns_first_element(): void
    {
        $collection = new ParameterCollection;
        $record1 = new ParameterRecord(name: 'first', value: 'value1');
        $record2 = new ParameterRecord(name: 'second', value: 'value2');

        $collection->add($record1, $record2);

        $this->assertSame($record1, $collection->firstItem());
    }

    public function test_firstItem_returns_null_when_collection_empty(): void
    {
        $collection = new ParameterCollection;

        $this->assertNull($collection->firstItem());
    }

    public function test_firstItem_returns_single_element(): void
    {
        $collection = new ParameterCollection;
        $record = new ParameterRecord(name: 'only', value: 'value');

        $collection->add($record);

        $this->assertSame($record, $collection->firstItem());
    }

    public function test_lastItem_returns_last_element(): void
    {
        $collection = new ParameterCollection;
        $record1 = new ParameterRecord(name: 'first', value: 'value1');
        $record2 = new ParameterRecord(name: 'second', value: 'value2');

        $collection->add($record1, $record2);

        $this->assertSame($record2, $collection->lastItem());
    }

    public function test_lastItem_returns_null_when_collection_empty(): void
    {
        $collection = new ParameterCollection;

        $this->assertNull($collection->lastItem());
    }

    public function test_lastItem_returns_single_element(): void
    {
        $collection = new ParameterCollection;
        $record = new ParameterRecord(name: 'only', value: 'value');

        $collection->add($record);

        $this->assertSame($record, $collection->lastItem());
    }

    public function test_firstItem_and_lastItem_return_same_element_when_only_one(): void
    {
        $collection = new ParameterCollection;
        $record = new ParameterRecord(name: 'unique', value: 'value');

        $collection->add($record);

        $this->assertSame($collection->firstItem(), $collection->lastItem());
        $this->assertSame($record, $collection->firstItem());
        $this->assertSame($record, $collection->lastItem());
    }
}
