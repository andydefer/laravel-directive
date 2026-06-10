<?php

// tests/Unit/Services/ParameterExtractorServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Contexts\ParameterParserContext;
use AndyDefer\Directive\Services\ParameterExtractorService;
use AndyDefer\Directive\Strategies\DefaultValueArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionalArgumentStrategy;
use AndyDefer\Directive\Strategies\OptionStrategy;
use AndyDefer\Directive\Strategies\RequiredArgumentStrategy;
use AndyDefer\Directive\Strategies\VariadicArgumentStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;

final class ParameterExtractorServiceTest extends UnitTestCase
{
    private ParameterExtractorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $context = new ParameterParserContext;
        $context->addStrategy(new RequiredArgumentStrategy);
        $context->addStrategy(new DefaultValueArgumentStrategy);
        $context->addStrategy(new OptionalArgumentStrategy);
        $context->addStrategy(new VariadicArgumentStrategy);
        $context->addStrategy(new OptionStrategy);

        $this->service = new ParameterExtractorService($context);
    }

    public function test_extract_required_arguments(): void
    {
        $result = $this->service->extract('user:create {name} {email}');

        $this->assertCount(2, $result);

        $first = $result->first();
        $this->assertSame('name', $first->name);
        $this->assertFalse($first->isOption);
        $this->assertTrue($first->required);
        $this->assertNull($first->default);
        $this->assertFalse($first->isVariadic);

        $second = $result->last();
        $this->assertSame('email', $second->name);
        $this->assertFalse($second->isOption);
        $this->assertTrue($second->required);
        $this->assertNull($second->default);
        $this->assertFalse($second->isVariadic);
    }

    public function test_extract_arguments_with_default_values(): void
    {
        $result = $this->service->extract('user:list {count=10}');

        $this->assertCount(1, $result);

        $item = $result->first();
        $this->assertSame('count', $item->name);
        $this->assertFalse($item->isOption);
        $this->assertFalse($item->required);
        $this->assertSame('10', $item->default);
        $this->assertFalse($item->isVariadic);
    }

    public function test_extract_optional_arguments(): void
    {
        $result = $this->service->extract('user:create {name?}');

        $this->assertCount(1, $result);

        $item = $result->first();
        $this->assertSame('name', $item->name);
        $this->assertFalse($item->isOption);
        $this->assertFalse($item->required);
        $this->assertNull($item->default);
        $this->assertFalse($item->isVariadic);
    }

    public function test_extract_variadic_arguments(): void
    {
        $result = $this->service->extract('process {files*}');

        $this->assertCount(1, $result);

        $item = $result->first();
        $this->assertSame('files', $item->name);
        $this->assertFalse($item->isOption);
        $this->assertFalse($item->required);
        $this->assertNull($item->default);
        $this->assertTrue($item->isVariadic);
    }

    public function test_extract_options(): void
    {
        $result = $this->service->extract('user:create {--role=} {--active}');

        $this->assertCount(2, $result);

        $first = $result->first();
        $this->assertSame('role', $first->name);
        $this->assertTrue($first->isOption);
        $this->assertFalse($first->required);
        $this->assertNull($first->default);
        $this->assertFalse($first->isVariadic);

        $second = $result->last();
        $this->assertSame('active', $second->name);
        $this->assertTrue($second->isOption);
        $this->assertFalse($second->required);
        $this->assertNull($second->default);
        $this->assertFalse($second->isVariadic);
    }

    public function test_extract_options_with_default_values(): void
    {
        $result = $this->service->extract('user:create {--role=admin}');

        $this->assertCount(1, $result);

        $item = $result->first();
        $this->assertSame('role', $item->name);
        $this->assertTrue($item->isOption);
        $this->assertFalse($item->required);
        $this->assertSame('admin', $item->default);
        $this->assertFalse($item->isVariadic);
    }

    public function test_extract_mixed_parameters(): void
    {
        $result = $this->service->extract('user:process {name} {role=user} {count?} {files*} {--force}');

        $this->assertCount(5, $result);
    }
}
