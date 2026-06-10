<?php

// tests/Directive/Unit/Services/DirectiveParserServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Unit\Services;

use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Enums\ParameterType;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use InvalidArgumentException;

final class DirectiveParserServiceTest extends UnitTestCase
{
    private DirectiveParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectiveParserService;
    }

    public function test_parse_with_arguments_only(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John Doe', 'john@example.com');

        $result = $this->service->parse('user:create {name} {email}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John Doe', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertTrue($parsed->options->isEmpty());
        $this->assertTrue($parsed->variadic_arguments->isEmpty());
    }

    public function test_parse_with_argument_default_value(): void
    {
        $arguments = new StringTypedCollection;

        $result = $this->service->parse('user:list {count=10}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('10', $parsed->arguments->get('count'));
    }

    public function test_parse_with_argument_default_value_overridden(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('5');

        $result = $this->service->parse('user:list {count=10}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('5', $parsed->arguments->get('count'));
    }

    public function test_parse_with_optional_argument(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John');

        $result = $this->service->parse('user:create {name?}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John', $parsed->arguments->get('name'));
    }

    public function test_parse_with_missing_optional_argument(): void
    {
        $arguments = new StringTypedCollection;

        $result = $this->service->parse('user:create {name?}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->arguments->isEmpty());
        $this->assertTrue($parsed->options->isEmpty());
        $this->assertTrue($parsed->variadic_arguments->isEmpty());
    }

    public function test_parse_with_missing_required_argument_throws_exception(): void
    {
        $arguments = new StringTypedCollection;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "name")');

        $this->service->parse('user:create {name}', $arguments);
    }

    public function test_parse_with_too_many_arguments_throws_exception(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John', 'Doe', 'Extra');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many arguments provided');

        $this->service->parse('user:create {first} {last}', $arguments);
    }

    public function test_parse_with_long_options(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John Doe', '--role=admin');

        $result = $this->service->parse('user:create {name} {--role=}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John Doe', $parsed->arguments->get('name'));
        $this->assertSame('admin', $parsed->options->get('role'));
    }

    public function test_parse_with_flag_option(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('--force');

        $result = $this->service->parse('cache:clear {--force}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->options->isFlag('force'));
        $this->assertTrue($parsed->options->isTrue('force'));
    }

    public function test_parse_with_short_option(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('-v');

        $result = $this->service->parse('app:run {-v}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->options->isFlag('v'));
        $this->assertTrue($parsed->options->isTrue('v'));
    }

    public function test_parse_with_mixed_arguments_and_options(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John', 'john@example.com', '--role=admin', '--active');

        $result = $this->service->parse('user:create {name} {email} {--role=} {--active}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertSame('admin', $parsed->options->get('role'));
        $this->assertTrue($parsed->options->isTrue('active'));
    }

    public function test_parse_with_options_between_arguments(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John', '--role=admin', 'john@example.com', '--active');

        $result = $this->service->parse('user:create {name} {email} {--role=} {--active}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertSame('admin', $parsed->options->get('role'));
        $this->assertTrue($parsed->options->isTrue('active'));
    }

    public function test_parse_with_option_without_value(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('--role=');

        $result = $this->service->parse('user:create {--role=}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->options->isFlag('role'));
        $this->assertTrue($parsed->options->isTrue('role'));
    }

    public function test_parse_with_variadic_argument(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('[', 'file1.txt,', 'file2.txt,', 'file3.txt', ']');

        $result = $this->service->parse('process {files*}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->arguments->isEmpty());
        $this->assertEquals(3, $parsed->variadic_arguments->count());

        $variadicArray = $parsed->variadic_arguments->toArray();
        $this->assertTrue(in_array('file1.txt', $variadicArray));
        $this->assertTrue(in_array('file2.txt', $variadicArray));
        $this->assertTrue(in_array('file3.txt', $variadicArray));
    }

    public function test_parse_with_required_and_variadic_arguments(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John Doe', '[', 'file1.txt,', 'file2.txt', ']');

        $result = $this->service->parse('user:process {name} {files*}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John Doe', $parsed->arguments->get('name'));
        $this->assertEquals(2, $parsed->variadic_arguments->count());

        $variadicArray = $parsed->variadic_arguments->toArray();
        $this->assertTrue(in_array('file1.txt', $variadicArray));
        $this->assertTrue(in_array('file2.txt', $variadicArray));
    }

    public function test_parse_with_variadic_but_no_values(): void
    {
        $arguments = new StringTypedCollection;

        $result = $this->service->parse('process {files*}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->arguments->isEmpty());
        $this->assertTrue($parsed->variadic_arguments->isEmpty());
    }

    public function test_parse_with_variadic_and_options(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('[', 'file1.txt,', 'file2.txt', ']', '--verbose');

        $result = $this->service->parse('process {files*} {--verbose}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertEquals(2, $parsed->variadic_arguments->count());
        $this->assertTrue($parsed->options->isTrue('verbose'));
    }

    public function test_invalid_order_variadic_before_optional_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('optional arguments must come before variadic arguments');

        $this->service->parse('user:create {files*} {name?}', new StringTypedCollection);
    }

    public function test_invalid_order_required_after_default_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('required arguments must come before arguments with default values');

        $this->service->parse('user:create {role=user} {name}', new StringTypedCollection);
    }

    public function test_invalid_order_required_after_optional_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('required arguments must come before optional arguments');

        $this->service->parse('user:create {name?} {email}', new StringTypedCollection);
    }

    public function test_invalid_order_default_after_optional_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('arguments with default values must come before optional arguments');

        $this->service->parse('user:create {name?} {role=user}', new StringTypedCollection);
    }

    public function test_valid_order_required_then_default_then_optional_then_variadic_then_options(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John', 'admin', '[', 'file1.txt,', 'file2.txt', ']', '--force');

        $result = $this->service->parse(
            'user:process {name} {role=user} {count?} {files*} {--force}',
            $arguments
        );
        $parsed = $this->service->toResult($result);

        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('admin', $parsed->arguments->get('role'));
        $this->assertNull($parsed->arguments->get('count'));
        $this->assertEquals(2, $parsed->variadic_arguments->count());

        $variadicArray = $parsed->variadic_arguments->toArray();
        $this->assertTrue(in_array('file1.txt', $variadicArray));
        $this->assertTrue(in_array('file2.txt', $variadicArray));
        $this->assertTrue($parsed->options->isTrue('force'));
    }

    public function test_extract_help_with_arguments(): void
    {
        $result = $this->service->extractHelp('user:create {name} {email}');

        $this->assertSame(2, $result->count());

        $first = $result->firstItem();
        $this->assertSame('name', $first->name);
        $this->assertSame(ParameterType::ARGUMENT, $first->type);
        $this->assertTrue($first->required);
        $this->assertNull($first->default);

        $second = $result->lastItem();
        $this->assertSame('email', $second->name);
        $this->assertSame(ParameterType::ARGUMENT, $second->type);
        $this->assertTrue($second->required);
        $this->assertNull($second->default);
    }

    public function test_extract_help_with_argument_default_value(): void
    {
        $result = $this->service->extractHelp('user:list {count=10}');

        $this->assertSame(1, $result->count());

        $item = $result->firstItem();
        $this->assertSame('count', $item->name);
        $this->assertSame(ParameterType::ARGUMENT, $item->type);
        $this->assertFalse($item->required);
        $this->assertSame('10', $item->default);
    }

    public function test_extract_help_with_optional_argument(): void
    {
        $result = $this->service->extractHelp('user:create {name?}');

        $this->assertSame(1, $result->count());

        $item = $result->firstItem();
        $this->assertSame('name', $item->name);
        $this->assertSame(ParameterType::ARGUMENT, $item->type);
        $this->assertFalse($item->required);
        $this->assertNull($item->default);
    }

    public function test_extract_help_with_options(): void
    {
        $result = $this->service->extractHelp('user:create {--role=} {--active}');

        $this->assertSame(2, $result->count());

        $first = $result->firstItem();
        $this->assertSame('role', $first->name);
        $this->assertSame(ParameterType::OPTION, $first->type);
        $this->assertFalse($first->required);
        $this->assertNull($first->default);

        $second = $result->lastItem();
        $this->assertSame('active', $second->name);
        $this->assertSame(ParameterType::OPTION, $second->type);
        $this->assertFalse($second->required);
        $this->assertNull($second->default);
    }

    public function test_extract_help_with_option_default_value(): void
    {
        $result = $this->service->extractHelp('user:create {--role=admin}');

        $this->assertSame(1, $result->count());

        $item = $result->firstItem();
        $this->assertSame('role', $item->name);
        $this->assertSame(ParameterType::OPTION, $item->type);
        $this->assertFalse($item->required);
        $this->assertSame('admin', $item->default);
    }

    public function test_extract_help_with_variadic_argument(): void
    {
        $result = $this->service->extractHelp('user:process {files*}');

        $this->assertSame(1, $result->count());

        $item = $result->firstItem();
        $this->assertSame('files', $item->name);
        $this->assertSame(ParameterType::VARIADIC_ARGUMENT, $item->type);
        $this->assertFalse($item->required);
        $this->assertNull($item->default);
    }

    public function test_extract_help_with_required_and_variadic_arguments(): void
    {
        $result = $this->service->extractHelp('user:process {name} {files*}');

        $this->assertSame(2, $result->count());

        $first = $result->firstItem();
        $this->assertSame('name', $first->name);
        $this->assertSame(ParameterType::ARGUMENT, $first->type);
        $this->assertTrue($first->required);

        $second = $result->lastItem();
        $this->assertSame('files', $second->name);
        $this->assertSame(ParameterType::VARIADIC_ARGUMENT, $second->type);
        $this->assertFalse($second->required);
    }

    public function test_to_result_converts_parsed_record_correctly(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John', '--role=admin', '--active');

        $parsed = $this->service->parse('user:create {name} {--role=} {--active}', $arguments);
        $result = $this->service->toResult($parsed);

        $this->assertInstanceOf(ParsedArgumentCollection::class, $result->arguments);
        $this->assertInstanceOf(ParsedOptionCollection::class, $result->options);
        $this->assertSame('John', $result->arguments->get('name'));
        $this->assertSame('admin', $result->options->get('role'));
        $this->assertTrue($result->options->isTrue('active'));
    }

    public function test_to_result_with_empty_parsed_record(): void
    {
        $arguments = new StringTypedCollection;

        $parsed = $this->service->parse('test:cmd', $arguments);
        $result = $this->service->toResult($parsed);

        $this->assertTrue($result->arguments->isEmpty());
        $this->assertTrue($result->options->isEmpty());
        $this->assertTrue($result->variadic_arguments->isEmpty());
    }

    public function test_to_result_with_variadic_arguments(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('[', 'file1.txt,', 'file2.txt,', 'file3.txt', ']');

        $parsed = $this->service->parse('process {files*}', $arguments);
        $result = $this->service->toResult($parsed);

        $this->assertTrue($result->arguments->isEmpty());
        $this->assertInstanceOf(StringTypedCollection::class, $result->variadic_arguments);
        $this->assertEquals(3, $result->variadic_arguments->count());

        $variadicArray = $result->variadic_arguments->toArray();
        $this->assertTrue(in_array('file1.txt', $variadicArray));
        $this->assertTrue(in_array('file2.txt', $variadicArray));
        $this->assertTrue(in_array('file3.txt', $variadicArray));
    }

    public function test_to_json_returns_valid_json(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John', '--role=admin');

        $parsed = $this->service->parse('user:create {name} {--role=}', $arguments);
        $json = $this->service->toJson($parsed);

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('arguments', $decoded);
        $this->assertArrayHasKey('options', $decoded);
        $this->assertArrayHasKey('name', $decoded['arguments']);
        $this->assertSame('John', $decoded['arguments']['name']);
        $this->assertArrayHasKey('role', $decoded['options']);
        $this->assertSame('admin', $decoded['options']['role']);
    }

    public function test_to_json_with_empty_parsed_record(): void
    {
        $arguments = new StringTypedCollection;

        $parsed = $this->service->parse('test:cmd', $arguments);
        $json = $this->service->toJson($parsed);

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertSame([], $decoded['arguments']);
        $this->assertSame([], $decoded['options']);
    }

    public function test_to_json_with_variadic_arguments(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('[', 'file1.txt,', 'file2.txt', ']');

        $parsed = $this->service->parse('process {files*}', $arguments);
        $json = $this->service->toJson($parsed);

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('variadic_arguments', $decoded);
        $this->assertCount(2, $decoded['variadic_arguments']);
        $this->assertEquals('file1.txt', $decoded['variadic_arguments'][0]);
        $this->assertEquals('file2.txt', $decoded['variadic_arguments'][1]);
    }

    public function test_parse_with_empty_signature(): void
    {
        $arguments = new StringTypedCollection;

        $result = $this->service->parse('test:cmd', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->arguments->isEmpty());
        $this->assertTrue($parsed->options->isEmpty());
        $this->assertTrue($parsed->variadic_arguments->isEmpty());
    }

    public function test_parse_with_multiple_short_options(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('-v', '-f', '--verbose');

        $result = $this->service->parse('test:cmd {-v} {-f} {--verbose}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->options->isTrue('v'));
        $this->assertTrue($parsed->options->isTrue('f'));
        $this->assertTrue($parsed->options->isTrue('verbose'));
    }

    public function test_parse_with_short_options_grouped(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('-vf');

        $result = $this->service->parse('test:cmd {-v} {-f}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->options->isTrue('v'));
        $this->assertTrue($parsed->options->isTrue('f'));
    }

    public function test_parse_with_option_value_containing_equals(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('--message=Hello=World');

        $result = $this->service->parse('test:cmd {--message=}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('Hello=World', $parsed->options->getValue('message'));
    }

    public function test_parse_with_false_option_value(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('--active=false');

        $result = $this->service->parse('test:cmd {--active}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('false', $parsed->options->getValue('active'));
        $this->assertFalse($parsed->options->isTrue('active'));
    }

    public function test_parse_with_multiple_arguments_and_defaults(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('John');

        $result = $this->service->parse('user:create {name} {role=user} {status=active}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('user', $parsed->arguments->get('role'));
        $this->assertSame('active', $parsed->arguments->get('status'));
    }

    public function test_parse_with_optional_argument_null_when_not_provided(): void
    {
        $arguments = new StringTypedCollection;

        $result = $this->service->parse('user:create {name?}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertNull($parsed->arguments->get('name'));
    }

    public function test_parse_with_variadic_and_trailing_options(): void
    {
        $arguments = new StringTypedCollection;
        $arguments->add('[', 'file1.txt,', 'file2.txt', ']', '--verbose', '--debug');

        $result = $this->service->parse('process {files*} {--verbose} {--debug}', $arguments);
        $parsed = $this->service->toResult($result);

        $this->assertEquals(2, $parsed->variadic_arguments->count());
        $this->assertTrue($parsed->options->isTrue('verbose'));
        $this->assertTrue($parsed->options->isTrue('debug'));
    }
}
