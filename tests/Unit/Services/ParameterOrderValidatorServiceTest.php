<?php
// tests/Unit/Services/ParameterOrderValidatorServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Contexts\ParameterParserContext;
use AndyDefer\Directive\Services\ParameterOrderValidatorService;
use AndyDefer\Directive\Strategies\DefaultValueArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionalArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionStrategy;
use AndyDefer\Directive\Strategies\RequiredArgumentStrategy;
use AndyDefer\Directive\Strategies\VariadicArgumentStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;
use InvalidArgumentException;

final class ParameterOrderValidatorServiceTest extends UnitTestCase
{
    private ParameterOrderValidatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $context = new ParameterParserContext();
        $context->addStrategy(new RequiredArgumentStrategy());
        $context->addStrategy(new DefaultValueArgumentStrategy());
        $context->addStrategy(new OptionalArgumentStrategy());
        $context->addStrategy(new VariadicArgumentStrategy());
        $context->addStrategy(new OptionStrategy());

        $this->service = new ParameterOrderValidatorService($context);
    }

    public function test_valid_order(): void
    {
        $this->service->validate([], '{name} {role=user} {count?} {files*} {--force}');
        $this->assertTrue(true);
    }

    public function test_invalid_order_required_after_default_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('required arguments must come before arguments with default values');

        $this->service->validate([], '{role=user} {name}');
    }

    public function test_invalid_order_required_after_optional_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('required arguments must come before optional arguments');

        $this->service->validate([], '{name?} {email}');
    }

    public function test_invalid_order_required_after_variadic_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('required arguments must come before variadic arguments');

        $this->service->validate([], '{files*} {name}');
    }

    public function test_invalid_order_required_after_option_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('required arguments must come before options');

        $this->service->validate([], '{--force} {name}');
    }

    public function test_invalid_order_default_after_optional_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('arguments with default values must come before optional arguments');

        $this->service->validate([], '{name?} {role=user}');
    }

    public function test_invalid_order_default_after_variadic_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('arguments with default values must come before variadic arguments');

        $this->service->validate([], '{files*} {role=user}');
    }

    public function test_invalid_order_default_after_option_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('arguments with default values must come before options');

        $this->service->validate([], '{--force} {role=user}');
    }

    public function test_invalid_order_optional_after_variadic_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('optional arguments must come before variadic arguments');

        $this->service->validate([], '{files*} {name?}');
    }

    public function test_invalid_order_optional_after_option_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('optional arguments must come before options');

        $this->service->validate([], '{--force} {name?}');
    }

    public function test_invalid_order_variadic_after_option_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('variadic arguments must come before options');

        $this->service->validate([], '{--force} {files*}');
    }
}
