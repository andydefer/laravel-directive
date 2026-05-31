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
        $this->service = new DirectiveParserService;
    }

    // ==================== Parse Tests ====================

    public function test_parse_with_arguments_only(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John Doe', 'john@example.com');

        $result = $this->service->parse('user:create {name} {email}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John Doe', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertTrue($parsed->options->isEmpty());
    }

    public function test_parse_with_argument_default_value(): void
    {
        $argv = new StringTypedCollection;

        $result = $this->service->parse('user:list {count=10}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertSame('10', $parsed->arguments->get('count'));
    }

    public function test_parse_with_argument_default_value_overridden(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('5');

        $result = $this->service->parse('user:list {count=10}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertSame('5', $parsed->arguments->get('count'));
    }

    public function test_parse_with_optional_argument(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John');

        $result = $this->service->parse('user:create {name?}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John', $parsed->arguments->get('name'));
    }

    public function test_parse_with_missing_optional_argument(): void
    {
        $argv = new StringTypedCollection;

        $result = $this->service->parse('user:create {name?}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->arguments->isEmpty());
        $this->assertTrue($parsed->options->isEmpty());
    }

    public function test_parse_with_missing_required_argument_throws_exception(): void
    {
        $argv = new StringTypedCollection;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "name")');

        $this->service->parse('user:create {name}', $argv);
    }

    public function test_parse_with_too_many_arguments_throws_exception(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John', 'Doe', 'Extra');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many arguments provided');

        $this->service->parse('user:create {first} {last}', $argv);
    }

    public function test_parse_with_long_options(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John Doe', '--role=admin');

        $result = $this->service->parse('user:create {name} {--role=}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John Doe', $parsed->arguments->get('name'));
        $this->assertSame('admin', $parsed->options->get('role'));
    }

    public function test_parse_with_flag_option(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('--force');

        $result = $this->service->parse('cache:clear {--force}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->options->get('force'));
    }

    public function test_parse_with_short_option(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('-v');

        $result = $this->service->parse('app:run {-v}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->options->get('v'));
    }

    public function test_parse_with_mixed_arguments_and_options(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John', 'john@example.com', '--role=admin', '--active');

        $result = $this->service->parse('user:create {name} {email} {--role=} {--active}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertSame('admin', $parsed->options->get('role'));
        $this->assertTrue($parsed->options->get('active'));
    }

    public function test_parse_with_options_between_arguments(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John', '--role=admin', 'john@example.com', '--active');

        $result = $this->service->parse('user:create {name} {email} {--role=} {--active}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertSame('admin', $parsed->options->get('role'));
        $this->assertTrue($parsed->options->get('active'));
    }

    public function test_parse_with_option_without_value(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('--role=');

        $result = $this->service->parse('user:create {--role=}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->options->get('role'));
    }

    // ==================== Order Validation Tests ====================

    public function test_invalid_order_required_after_default_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required arguments must come before arguments with default values');

        $this->service->parse('user:create {role=user} {name}', new StringTypedCollection);
    }

    public function test_invalid_order_required_after_optional_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required arguments must come before arguments with default values');

        $this->service->parse('user:create {name?} {email}', new StringTypedCollection);
    }

    public function test_invalid_order_required_after_option_throws_exception(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required arguments must come before arguments with default values');

        $this->service->parse('user:create {--force} {name}', $argv);
    }

    public function test_invalid_order_default_after_option_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Arguments with default values must come before optional arguments and options');

        $this->service->parse('user:create {--force} {role=user}', new StringTypedCollection);
    }

    public function test_invalid_order_optional_after_option_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Optional arguments must come before options');

        $this->service->parse('user:create {--force} {name?}', new StringTypedCollection);
    }

    public function test_valid_order_required_then_default_then_optional_then_options(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John');

        $result = $this->service->parse('user:create {name} {role=user} {count?} {--force}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('user', $parsed->arguments->get('role'));
        $this->assertNull($parsed->arguments->get('count'));
        $this->assertNull($parsed->options->get('force'));
    }

    // ==================== ExtractHelp Tests ====================

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

    // ==================== ToResult Tests ====================

    public function test_to_result_converts_parsed_record_correctly(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John', '--role=admin', '--active');

        $parsed = $this->service->parse('user:create {name} {--role=} {--active}', $argv);
        $result = $this->service->toResult($parsed);

        $this->assertInstanceOf(ParameterCollection::class, $result->arguments);
        $this->assertInstanceOf(ParameterCollection::class, $result->options);
        $this->assertSame('John', $result->arguments->get('name'));
        $this->assertSame('admin', $result->options->get('role'));
        $this->assertTrue($result->options->get('active'));
    }

    public function test_to_result_with_empty_parsed_record(): void
    {
        $argv = new StringTypedCollection;

        $parsed = $this->service->parse('test:cmd', $argv);
        $result = $this->service->toResult($parsed);

        $this->assertTrue($result->arguments->isEmpty());
        $this->assertTrue($result->options->isEmpty());
    }

    // ==================== ToJson Tests ====================

    public function test_to_json_returns_valid_json(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John', '--role=admin');

        $parsed = $this->service->parse('user:create {name} {--role=}', $argv);
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
        $argv = new StringTypedCollection;

        $parsed = $this->service->parse('test:cmd', $argv);
        $json = $this->service->toJson($parsed);

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertSame([], $decoded['arguments']);
        $this->assertSame([], $decoded['options']);
    }

    // ==================== Helper Methods Tests ====================

    public function test_parse_with_multiple_short_options(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('-v', '-f', '--verbose');

        $result = $this->service->parse('test:cmd {-v} {-f} {--verbose}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->options->get('v'));
        $this->assertTrue($parsed->options->get('f'));
        $this->assertTrue($parsed->options->get('verbose'));
    }

    public function test_parse_with_short_options_grouped(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('-vf');

        $result = $this->service->parse('test:cmd {-v} {-f}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertTrue($parsed->options->get('v'));
        $this->assertTrue($parsed->options->get('f'));
    }

    public function test_parse_with_option_value_containing_equals(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('--message=Hello=World');

        $result = $this->service->parse('test:cmd {--message=}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertSame('Hello=World', $parsed->options->get('message'));
    }

    public function test_parse_with_false_option_value(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('--active=false');

        $result = $this->service->parse('test:cmd {--active}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertFalse($parsed->options->get('active'));
    }

    public function test_parse_with_multiple_arguments_and_defaults(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('John');

        $result = $this->service->parse('user:create {name} {role=user} {status=active}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('user', $parsed->arguments->get('role'));
        $this->assertSame('active', $parsed->arguments->get('status'));
    }

    public function test_parse_with_optional_argument_null_when_not_provided(): void
    {
        $argv = new StringTypedCollection;

        $result = $this->service->parse('user:create {name?}', $argv);
        $parsed = $this->service->toResult($result);

        $this->assertNull($parsed->arguments->get('name'));
    }
}
