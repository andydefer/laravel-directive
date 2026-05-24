<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Unit\Services;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Enums\ParameterType;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

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
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('John Doe', 'john@example.com');

        // Act
        $result = $this->service->parse('user:create {name} {email}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertSame('John Doe', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertTrue($parsed->options->isEmpty());
    }

    public function test_parse_with_long_options(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('John Doe', '--role=admin');

        // Act
        $result = $this->service->parse('user:create {name} {--role=}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertSame('John Doe', $parsed->arguments->get('name'));
        $this->assertSame('admin', $parsed->options->get('role'));
    }

    public function test_parse_with_flag_option(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('--force');

        // Act
        $result = $this->service->parse('cache:clear {--force}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertTrue($parsed->options->get('force'));
    }

    public function test_parse_with_short_option(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('-v');

        // Act
        $result = $this->service->parse('app:run {-v}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertTrue($parsed->options->get('v'));
    }

    public function test_parse_with_mixed_arguments_and_options(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('John', 'john@example.com', '--role=admin', '--active');

        // Act
        $result = $this->service->parse('user:create {name} {email} {--role=} {--active}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertSame('admin', $parsed->options->get('role'));
        $this->assertTrue($parsed->options->get('active'));
    }

    public function test_parse_with_options_between_arguments(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('John', '--role=admin', 'john@example.com', '--active');

        // Act
        $result = $this->service->parse('user:create {name} {email} {--role=} {--active}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertSame('John', $parsed->arguments->get('name'));
        $this->assertSame('john@example.com', $parsed->arguments->get('email'));
        $this->assertSame('admin', $parsed->options->get('role'));
        $this->assertTrue($parsed->options->get('active'));
    }

    public function test_parse_with_optional_argument(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('John');

        // Act
        $result = $this->service->parse('user:create {name?}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertSame('John', $parsed->arguments->get('name'));
    }

    public function test_parse_with_missing_optional_argument(): void
    {
        // Arrange
        $argv = new StringTypedCollection;

        // Act
        $result = $this->service->parse('user:create {name?}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertTrue($parsed->arguments->isEmpty());
        $this->assertTrue($parsed->options->isEmpty());
    }

    public function test_parse_with_option_without_value(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('--role=');

        // Act
        $result = $this->service->parse('user:create {--role=}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertTrue($parsed->options->get('role'));
    }

    // ==================== ExtractHelp Tests ====================

    public function test_extract_help_with_arguments(): void
    {
        // Act
        $result = $this->service->extractHelp('user:create {name} {email}');

        // Assert
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

    public function test_extract_help_with_options(): void
    {
        // Act
        $result = $this->service->extractHelp('user:create {--role=} {--active}');

        // Assert
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
        // Act
        $result = $this->service->extractHelp('user:create {--role=admin}');

        // Assert
        $this->assertSame(1, $result->count());

        $item = $result->firstItem();
        $this->assertSame('role', $item->name);
        $this->assertSame(ParameterType::OPTION, $item->type);
        $this->assertFalse($item->required);
        $this->assertSame('admin', $item->default);
    }

    public function test_extract_help_with_optional_argument(): void
    {
        // Act
        $result = $this->service->extractHelp('user:create {name?}');

        // Assert
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
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('John', '--role=admin', '--active');

        // Act
        $parsed = $this->service->parse('user:create {name} {--role=} {--active}', $argv);
        $result = $this->service->toResult($parsed);

        // Assert
        $this->assertInstanceOf(ParameterCollection::class, $result->arguments);
        $this->assertInstanceOf(ParameterCollection::class, $result->options);
        $this->assertSame('John', $result->arguments->get('name'));
        $this->assertSame('admin', $result->options->get('role'));
        $this->assertTrue($result->options->get('active'));
    }

    public function test_to_result_with_empty_parsed_record(): void
    {
        // Arrange
        $argv = new StringTypedCollection;

        // Act
        $parsed = $this->service->parse('test:cmd', $argv);
        $result = $this->service->toResult($parsed);

        // Assert
        $this->assertTrue($result->arguments->isEmpty());
        $this->assertTrue($result->options->isEmpty());
    }

    // ==================== ToJson Tests ====================

    public function test_to_json_returns_valid_json(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('John', '--role=admin');

        // Act
        $parsed = $this->service->parse('user:create {name} {--role=}', $argv);
        $json = $this->service->toJson($parsed);

        // Assert
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
        // Arrange
        $argv = new StringTypedCollection;

        // Act
        $parsed = $this->service->parse('test:cmd', $argv);
        $json = $this->service->toJson($parsed);

        // Assert
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertSame([], $decoded['arguments']);
        $this->assertSame([], $decoded['options']);
    }

    // ==================== Helper Methods Tests ====================

    public function test_parse_with_multiple_short_options(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('-v', '-f', '--verbose');

        // Act
        $result = $this->service->parse('test:cmd {-v} {-f} {--verbose}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertTrue($parsed->options->get('v'));
        $this->assertTrue($parsed->options->get('f'));
        $this->assertTrue($parsed->options->get('verbose'));
    }

    public function test_parse_with_option_value_containing_equals(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('--message=Hello=World');

        // Act
        $result = $this->service->parse('test:cmd {--message=}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertSame('Hello=World', $parsed->options->get('message'));
    }

    public function test_parse_with_false_option_value(): void
    {
        // Arrange
        $argv = new StringTypedCollection;
        $argv->add('--active=false');

        // Act
        $result = $this->service->parse('test:cmd {--active}', $argv);
        $parsed = $this->service->toResult($result);

        // Assert
        $this->assertFalse($parsed->options->get('active'));
    }
}
