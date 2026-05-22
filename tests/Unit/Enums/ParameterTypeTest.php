<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Enums;

use AndyDefer\Directive\Enums\ParameterType;
use PHPUnit\Framework\TestCase;

final class ParameterTypeTest extends TestCase
{
    public function test_values(): void
    {
        $values = ParameterType::values();

        $this->assertSame(['argument', 'option'], $values);
    }

    public function test_is_argument(): void
    {
        $this->assertTrue(ParameterType::ARGUMENT->isArgument());
        $this->assertFalse(ParameterType::OPTION->isArgument());
    }

    public function test_is_option(): void
    {
        $this->assertTrue(ParameterType::OPTION->isOption());
        $this->assertFalse(ParameterType::ARGUMENT->isOption());
    }

    public function test_get_label(): void
    {
        $this->assertSame('Argument', ParameterType::ARGUMENT->getLabel());
        $this->assertSame('Option', ParameterType::OPTION->getLabel());
    }

    public function test_from_value(): void
    {
        $this->assertSame(ParameterType::ARGUMENT, ParameterType::fromValue('argument'));
        $this->assertSame(ParameterType::OPTION, ParameterType::fromValue('option'));
        $this->assertNull(ParameterType::fromValue('invalid'));
    }
}
