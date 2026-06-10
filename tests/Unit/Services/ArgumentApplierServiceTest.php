<?php

// tests/Unit/Services/ArgumentApplierServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\ExtractedParameterCollection;
use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Records\ExtractedParameterRecord;
use AndyDefer\Directive\Services\ArgumentApplierService;
use AndyDefer\Directive\Tests\UnitTestCase;
use InvalidArgumentException;

final class ArgumentApplierServiceTest extends UnitTestCase
{
    private ArgumentApplierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ArgumentApplierService;
    }

    public function test_apply_with_required_arguments(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'name',
            isOption: false,
            required: true,
            default: null,
            raw: 'name',
            isVariadic: false
        ));
        $parameters->add(new ExtractedParameterRecord(
            name: 'email',
            isOption: false,
            required: true,
            default: null,
            raw: 'email',
            isVariadic: false
        ));

        $providedArguments = ['John Doe', 'john@example.com'];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);

        $this->assertSame('John Doe', $arguments->get('name'));
        $this->assertSame('john@example.com', $arguments->get('email'));
        $this->assertEmpty($variadicArguments);
    }

    public function test_apply_with_default_values(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'count',
            isOption: false,
            required: false,
            default: '10',
            raw: 'count=10',
            isVariadic: false
        ));

        $providedArguments = [];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);

        $this->assertSame('10', $arguments->get('count'));
        $this->assertEmpty($variadicArguments);
    }

    public function test_apply_with_default_value_overridden(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'count',
            isOption: false,
            required: false,
            default: '10',
            raw: 'count=10',
            isVariadic: false
        ));

        $providedArguments = ['5'];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);

        $this->assertSame('5', $arguments->get('count'));
        $this->assertEmpty($variadicArguments);
    }

    public function test_apply_with_optional_argument(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'name',
            isOption: false,
            required: false,
            default: null,
            raw: 'name?',
            isVariadic: false
        ));

        $providedArguments = ['John'];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);

        $this->assertSame('John', $arguments->get('name'));
        $this->assertEmpty($variadicArguments);
    }

    public function test_apply_with_missing_optional_argument(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'name',
            isOption: false,
            required: false,
            default: null,
            raw: 'name?',
            isVariadic: false
        ));

        $providedArguments = [];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);

        $this->assertNull($arguments->get('name'));
        $this->assertEmpty($variadicArguments);
    }

    public function test_apply_with_missing_required_argument_throws_exception(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'name',
            isOption: false,
            required: true,
            default: null,
            raw: 'name',
            isVariadic: false
        ));

        $providedArguments = [];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "name")');

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);
    }

    public function test_apply_with_too_many_arguments_throws_exception(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'first',
            isOption: false,
            required: true,
            default: null,
            raw: 'first',
            isVariadic: false
        ));
        $parameters->add(new ExtractedParameterRecord(
            name: 'last',
            isOption: false,
            required: true,
            default: null,
            raw: 'last',
            isVariadic: false
        ));

        $providedArguments = ['John', 'Doe', 'Extra'];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many arguments provided');

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);
    }

    public function test_apply_with_variadic_arguments(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'files',
            isOption: false,
            required: false,
            default: null,
            raw: 'files*',
            isVariadic: true
        ));

        $providedArguments = ['file1.txt', 'file2.txt', 'file3.txt'];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);

        $this->assertTrue($arguments->isEmpty());
        $this->assertCount(3, $variadicArguments);
        $this->assertEquals('file1.txt', $variadicArguments[0]);
        $this->assertEquals('file2.txt', $variadicArguments[1]);
        $this->assertEquals('file3.txt', $variadicArguments[2]);
    }

    public function test_apply_with_required_and_variadic_arguments(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'name',
            isOption: false,
            required: true,
            default: null,
            raw: 'name',
            isVariadic: false
        ));
        $parameters->add(new ExtractedParameterRecord(
            name: 'files',
            isOption: false,
            required: false,
            default: null,
            raw: 'files*',
            isVariadic: true
        ));

        $providedArguments = ['John Doe', 'file1.txt', 'file2.txt'];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);

        $this->assertSame('John Doe', $arguments->get('name'));
        $this->assertCount(2, $variadicArguments);
        $this->assertEquals('file1.txt', $variadicArguments[0]);
        $this->assertEquals('file2.txt', $variadicArguments[1]);
    }

    public function test_apply_with_variadic_and_no_arguments(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'files',
            isOption: false,
            required: false,
            default: null,
            raw: 'files*',
            isVariadic: true
        ));

        $providedArguments = [];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);

        $this->assertTrue($arguments->isEmpty());
        $this->assertEmpty($variadicArguments);
    }

    public function test_apply_with_multiple_arguments_and_defaults(): void
    {
        $parameters = new ExtractedParameterCollection;
        $parameters->add(new ExtractedParameterRecord(
            name: 'name',
            isOption: false,
            required: true,
            default: null,
            raw: 'name',
            isVariadic: false
        ));
        $parameters->add(new ExtractedParameterRecord(
            name: 'role',
            isOption: false,
            required: false,
            default: 'user',
            raw: 'role=user',
            isVariadic: false
        ));
        $parameters->add(new ExtractedParameterRecord(
            name: 'status',
            isOption: false,
            required: false,
            default: 'active',
            raw: 'status=active',
            isVariadic: false
        ));

        $providedArguments = ['John'];
        $arguments = new ParsedArgumentCollection;
        $variadicArguments = [];

        $this->service->apply($parameters, $providedArguments, $arguments, $variadicArguments);

        $this->assertSame('John', $arguments->get('name'));
        $this->assertSame('user', $arguments->get('role'));
        $this->assertSame('active', $arguments->get('status'));
        $this->assertEmpty($variadicArguments);
    }
}
