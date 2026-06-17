<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Collections;

use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\PhpServices\Enums\PrimitiveType;

final class AbstractKeyValueCollectionTest extends UnitTestCase
{
    public function test_first_item_returns_first_element(): void
    {
        $collection = new ParameterVOCollection;
        $vo1 = new ParameterVO(name: 'first', value: 'value1', type: PrimitiveType::STRING);
        $vo2 = new ParameterVO(name: 'second', value: 'value2', type: PrimitiveType::STRING);

        $collection->add($vo1, $vo2);

        $this->assertSame($vo1, $collection->first());
    }

    public function test_first_item_returns_null_when_collection_empty(): void
    {
        $collection = new ParameterVOCollection;

        $this->assertNull($collection->first());
    }

    public function test_first_item_returns_single_element(): void
    {
        $collection = new ParameterVOCollection;
        $vo = new ParameterVO(name: 'only', value: 'value', type: PrimitiveType::STRING);

        $collection->add($vo);

        $this->assertSame($vo, $collection->first());
    }

    public function test_last_item_returns_last_element(): void
    {
        $collection = new ParameterVOCollection;
        $vo1 = new ParameterVO(name: 'first', value: 'value1', type: PrimitiveType::STRING);
        $vo2 = new ParameterVO(name: 'second', value: 'value2', type: PrimitiveType::STRING);

        $collection->add($vo1, $vo2);

        $this->assertSame($vo2, $collection->last());
    }

    public function test_last_item_returns_null_when_collection_empty(): void
    {
        $collection = new ParameterVOCollection;

        $this->assertNull($collection->last());
    }

    public function test_last_item_returns_single_element(): void
    {
        $collection = new ParameterVOCollection;
        $vo = new ParameterVO(name: 'only', value: 'value', type: PrimitiveType::STRING);

        $collection->add($vo);

        $this->assertSame($vo, $collection->last());
    }

    public function test_first_item_and_last_item_return_same_element_when_only_one(): void
    {
        $collection = new ParameterVOCollection;
        $vo = new ParameterVO(name: 'unique', value: 'value', type: PrimitiveType::STRING);

        $collection->add($vo);

        $this->assertSame($collection->first(), $collection->last());
        $this->assertSame($vo, $collection->first());
        $this->assertSame($vo, $collection->last());
    }
}
