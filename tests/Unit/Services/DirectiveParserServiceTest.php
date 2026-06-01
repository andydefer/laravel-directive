<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Unit\Services;

use AndyDefer\Directive\Collections\ParameterCollection;
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
        $this->service = new DirectiveParserService();
    }

    // ==================== Parse Tests ====================

    public function test_parse_with_arguments_only(): void
    {
        // Arrange: Create arguments collection
        $arguments = new StringTypedCollection();
        $arguments->add('John Doe', 'john@example.com');

        // Act: Parse the signature with arguments
        $result = $this->service->parse('user:create {name} {email}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify arguments are correctly parsed
        $this->assertSame('John Doe', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertTrue($parsed->options->isEmpty());
    }

    public function test_parse_with_argument_default_value(): void
    {
        // Arrange: Create empty arguments collection
        $arguments = new StringTypedCollection();

        // Act: Parse with default value
        $result = $this->service->parse('user:list {count=10}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify default value is applied
        $this->assertSame('10', $parsed->arguments->get('count'));
    }

    public function test_parse_with_argument_default_value_overridden(): void
    {
        // Arrange: Create arguments collection with override
        $arguments = new StringTypedCollection();
        $arguments->add('5');

        // Act: Parse with provided value overriding default
        $result = $this->service->parse('user:list {count=10}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify provided value is used
        $this->assertSame('5', $parsed->arguments->get('count'));
    }

    public function test_parse_with_optional_argument(): void
    {
        // Arrange: Create arguments collection
        $arguments = new StringTypedCollection();
        $arguments->add('John');

        // Act: Parse with optional argument
        $result = $this->service->parse('user:create {name?}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify optional argument is captured
        $this->assertSame('John', $parsed->arguments->get('name'));
    }

    public function test_parse_with_missing_optional_argument(): void
    {
        // Arrange: Create empty arguments collection
        $arguments = new StringTypedCollection();

        // Act: Parse with missing optional argument
        $result = $this->service->parse('user:create {name?}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify no argument is added
        $this->assertTrue($parsed->arguments->isEmpty());
        $this->assertTrue($parsed->options->isEmpty());
    }

    public function test_parse_with_missing_required_argument_throws_exception(): void
    {
        // Arrange: Create empty arguments collection
        $arguments = new StringTypedCollection();

        // Assert: Expect invalid argument exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "name")');

        // Act: Parse with missing required argument
        $this->service->parse('user:create {name}', $arguments);
    }

    public function test_parse_with_too_many_arguments_throws_exception(): void
    {
        // Arrange: Create arguments collection with extra argument
        $arguments = new StringTypedCollection();
        $arguments->add('John', 'Doe', 'Extra');

        // Assert: Expect invalid argument exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many arguments provided');

        // Act: Parse with too many arguments
        $this->service->parse('user:create {first} {last}', $arguments);
    }

    public function test_parse_with_long_options(): void
    {
        // Arrange: Create arguments collection with option
        $arguments = new StringTypedCollection();
        $arguments->add('John Doe', '--role=admin');

        // Act: Parse with long option
        $result = $this->service->parse('user:create {name} {--role=}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify option is correctly parsed
        $this->assertSame('John Doe', $parsed->arguments->get('name'));
        $this->assertSame('admin', $parsed->options->get('role'));
    }

    public function test_parse_with_flag_option(): void
    {
        // Arrange: Create arguments collection with flag
        $arguments = new StringTypedCollection();
        $arguments->add('--force');

        // Act: Parse with flag option
        $result = $this->service->parse('cache:clear {--force}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify flag is set to true
        $this->assertTrue($parsed->options->get('force'));
    }

    public function test_parse_with_short_option(): void
    {
        // Arrange: Create arguments collection with short option
        $arguments = new StringTypedCollection();
        $arguments->add('-v');

        // Act: Parse with short option
        $result = $this->service->parse('app:run {-v}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify short option is set to true
        $this->assertTrue($parsed->options->get('v'));
    }

    public function test_parse_with_mixed_arguments_and_options(): void
    {
        // Arrange: Create arguments collection with mixed content
        $arguments = new StringTypedCollection();
        $arguments->add('John', 'john@example.com', '--role=admin', '--active');

        // Act: Parse with mixed arguments and options
        $result = $this->service->parse('user:create {name} {email} {--role=} {--active}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify all are correctly parsed
        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertSame('admin', $parsed->options->get('role'));
        $this->assertTrue($parsed->options->get('active'));
    }

    public function test_parse_with_options_between_arguments(): void
    {
        // Arrange: Create arguments collection with options interleaved
        $arguments = new StringTypedCollection();
        $arguments->add('John', '--role=admin', 'john@example.com', '--active');

        // Act: Parse with options between arguments
        $result = $this->service->parse('user:create {name} {email} {--role=} {--active}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify all are correctly parsed regardless of order
        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertSame('admin', $parsed->options->get('role'));
        $this->assertTrue($parsed->options->get('active'));
    }

    public function test_parse_with_option_without_value(): void
    {
        // Arrange: Create arguments collection with empty option
        $arguments = new StringTypedCollection();
        $arguments->add('--role=');

        // Act: Parse with option that has no value
        $result = $this->service->parse('user:create {--role=}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify empty option is treated as true
        $this->assertTrue($parsed->options->get('role'));
    }

    // ==================== Order Validation Tests ====================

    public function test_invalid_order_required_after_default_throws_exception(): void
    {
        // Assert: Expect invalid argument exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required arguments must come before arguments with default values');

        // Act: Parse with invalid order
        $this->service->parse('user:create {role=user} {name}', new StringTypedCollection());
    }

    public function test_invalid_order_required_after_optional_throws_exception(): void
    {
        // Assert: Expect invalid argument exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required arguments must come before arguments with default values');

        // Act: Parse with invalid order
        $this->service->parse('user:create {name?} {email}', new StringTypedCollection());
    }

    public function test_invalid_order_required_after_option_throws_exception(): void
    {
        // Arrange: Create arguments collection
        $arguments = new StringTypedCollection();
        $arguments->add('John');

        // Assert: Expect invalid argument exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required arguments must come before arguments with default values');

        // Act: Parse with invalid order
        $this->service->parse('user:create {--force} {name}', $arguments);
    }

    public function test_invalid_order_default_after_option_throws_exception(): void
    {
        // Assert: Expect invalid argument exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Arguments with default values must come before optional arguments and options');

        // Act: Parse with invalid order
        $this->service->parse('user:create {--force} {role=user}', new StringTypedCollection());
    }

    public function test_invalid_order_optional_after_option_throws_exception(): void
    {
        // Assert: Expect invalid argument exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Optional arguments must come before options');

        // Act: Parse with invalid order
        $this->service->parse('user:create {--force} {name?}', new StringTypedCollection());
    }

    public function test_valid_order_required_then_default_then_optional_then_options(): void
    {
        // Arrange: Create arguments collection
        $arguments = new StringTypedCollection();
        $arguments->add('John');

        // Act: Parse with valid order
        $result = $this->service->parse('user:create {name} {role=user} {count?} {--force}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify all are correctly parsed
        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('user', $parsed->arguments->get('role'));
        $this->assertNull($parsed->arguments->get('count'));
        $this->assertNull($parsed->options->get('force'));
    }

    // ==================== ExtractHelp Tests ====================

    public function test_extract_help_with_arguments(): void
    {
        // Act: Extract help from signature with arguments
        $result = $this->service->extractHelp('user:create {name} {email}');

        // Assert: Verify argument help is correct
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
        // Act: Extract help from signature with default value
        $result = $this->service->extractHelp('user:list {count=10}');

        // Assert: Verify default value is captured
        $this->assertSame(1, $result->count());

        $item = $result->firstItem();
        $this->assertSame('count', $item->name);
        $this->assertSame(ParameterType::ARGUMENT, $item->type);
        $this->assertFalse($item->required);
        $this->assertSame('10', $item->default);
    }

    public function test_extract_help_with_options(): void
    {
        // Act: Extract help from signature with options
        $result = $this->service->extractHelp('user:create {--role=} {--active}');

        // Assert: Verify option help is correct
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
        // Act: Extract help from signature with option default
        $result = $this->service->extractHelp('user:create {--role=admin}');

        // Assert: Verify option default is captured
        $this->assertSame(1, $result->count());

        $item = $result->firstItem();
        $this->assertSame('role', $item->name);
        $this->assertSame(ParameterType::OPTION, $item->type);
        $this->assertFalse($item->required);
        $this->assertSame('admin', $item->default);
    }

    public function test_extract_help_with_optional_argument(): void
    {
        // Act: Extract help from signature with optional argument
        $result = $this->service->extractHelp('user:create {name?}');

        // Assert: Verify optional argument is marked not required
        $this->assertSame(1, $result->count());

        $item = $result->firstItem();
        $this->assertSame('name', $item->name);
        $this->assertSame(ParameterType::ARGUMENT, $item->type);
        $this->assertFalse($item->required);
        $this->assertNull($item->default);
    }

    // ==================== ToResult Tests ====================

    public function test_to_result_converts_parsed_record_correctly(): void
    {
        // Arrange: Create arguments collection
        $arguments = new StringTypedCollection();
        $arguments->add('John', '--role=admin', '--active');

        // Act: Parse and convert to result
        $parsed = $this->service->parse('user:create {name} {--role=} {--active}', $arguments);
        $result = $this->service->toResult($parsed);

        // Assert: Verify result structure
        $this->assertInstanceOf(ParameterCollection::class, $result->arguments);
        $this->assertInstanceOf(ParameterCollection::class, $result->options);
        $this->assertSame('John', $result->arguments->get('name'));
        $this->assertSame('admin', $result->options->get('role'));
        $this->assertTrue($result->options->get('active'));
    }

    public function test_to_result_with_empty_parsed_record(): void
    {
        // Arrange: Create empty arguments collection
        $arguments = new StringTypedCollection();

        // Act: Parse empty signature
        $parsed = $this->service->parse('test:cmd', $arguments);
        $result = $this->service->toResult($parsed);

        // Assert: Verify empty result
        $this->assertTrue($result->arguments->isEmpty());
        $this->assertTrue($result->options->isEmpty());
    }

    // ==================== ToJson Tests ====================

    public function test_to_json_returns_valid_json(): void
    {
        // Arrange: Create arguments collection
        $arguments = new StringTypedCollection();
        $arguments->add('John', '--role=admin');

        // Act: Parse and convert to JSON
        $parsed = $this->service->parse('user:create {name} {--role=}', $arguments);
        $json = $this->service->toJson($parsed);

        // Assert: Verify JSON structure
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
        // Arrange: Create empty arguments collection
        $arguments = new StringTypedCollection();

        // Act: Parse and convert to JSON
        $parsed = $this->service->parse('test:cmd', $arguments);
        $json = $this->service->toJson($parsed);

        // Assert: Verify empty JSON structure
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertSame([], $decoded['arguments']);
        $this->assertSame([], $decoded['options']);
    }

    // ==================== Helper Methods Tests ====================

    public function test_parse_with_multiple_short_options(): void
    {
        // Arrange: Create arguments collection with multiple short options
        $arguments = new StringTypedCollection();
        $arguments->add('-v', '-f', '--verbose');

        // Act: Parse multiple short options
        $result = $this->service->parse('test:cmd {-v} {-f} {--verbose}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify all options are captured
        $this->assertTrue($parsed->options->get('v'));
        $this->assertTrue($parsed->options->get('f'));
        $this->assertTrue($parsed->options->get('verbose'));
    }

    public function test_parse_with_short_options_grouped(): void
    {
        // Arrange: Create arguments collection with grouped short options
        $arguments = new StringTypedCollection();
        $arguments->add('-vf');

        // Act: Parse grouped short options
        $result = $this->service->parse('test:cmd {-v} {-f}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify grouped options are expanded correctly
        $this->assertTrue($parsed->options->get('v'));
        $this->assertTrue($parsed->options->get('f'));
    }

    public function test_parse_with_option_value_containing_equals(): void
    {
        // Arrange: Create arguments collection with value containing equals
        $arguments = new StringTypedCollection();
        $arguments->add('--message=Hello=World');

        // Act: Parse option with complex value
        $result = $this->service->parse('test:cmd {--message=}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify value is preserved correctly
        $this->assertSame('Hello=World', $parsed->options->get('message'));
    }

    public function test_parse_with_false_option_value(): void
    {
        // Arrange: Create arguments collection with false value
        $arguments = new StringTypedCollection();
        $arguments->add('--active=false');

        // Act: Parse false option
        $result = $this->service->parse('test:cmd {--active}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify option is set to false
        $this->assertFalse($parsed->options->get('active'));
    }

    public function test_parse_with_multiple_arguments_and_defaults(): void
    {
        // Arrange: Create arguments collection
        $arguments = new StringTypedCollection();
        $arguments->add('John');

        // Act: Parse with multiple defaults
        $result = $this->service->parse('user:create {name} {role=user} {status=active}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify defaults are applied correctly
        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('user', $parsed->arguments->get('role'));
        $this->assertSame('active', $parsed->arguments->get('status'));
    }

    public function test_parse_with_optional_argument_null_when_not_provided(): void
    {
        // Arrange: Create empty arguments collection
        $arguments = new StringTypedCollection();

        // Act: Parse with optional argument
        $result = $this->service->parse('user:create {name?}', $arguments);
        $parsed = $this->service->toResult($result);

        // Assert: Verify argument is null when not provided
        $this->assertNull($parsed->arguments->get('name'));
    }
}
