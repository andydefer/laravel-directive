<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Unit\Services;

use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\SignatureParser;

final class DirectiveParserServiceTest extends UnitTestCase
{
    private DirectiveParserService $service;

    private SignatureParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SignatureParser;
        $this->service = new DirectiveParserService($this->parser);
    }

    public function test_parse_with_arguments_only(): void
    {
        // Arrange
        $signature = 'user:create {name} {email}';
        $query = 'user:create John^Doe john@example.com';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertInstanceOf(ParsedSignatureRecord::class, $result);
        $this->assertSame('user:create', $result->source);
        $this->assertSame('John Doe', $result->required->first()->value);
        $this->assertSame('john@example.com', $result->required->last()->value);
        $this->assertCount(0, $result->default);
        $this->assertCount(0, $result->variadic);
        $this->assertCount(0, $result->options);
    }

    public function test_parse_with_argument_default_value(): void
    {
        // Arrange
        $signature = 'user:list {count=10}';
        $query = 'user:list';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertSame('user:list', $result->source);
        $this->assertSame('10', $result->default->first()->value);
    }

    public function test_parse_with_argument_default_value_overridden(): void
    {
        // Arrange
        $signature = 'user:list {count=10}';
        $query = 'user:list 5';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertSame('5', $result->default->first()->value);
    }

    public function test_parse_with_required_and_default_arguments(): void
    {
        // Arrange
        $signature = 'user:create {name} {role=user}';
        $query = 'user:create John';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertSame('John', $result->required->first()->value);
        $this->assertSame('user', $result->default->first()->value);
    }

    public function test_parse_with_options(): void
    {
        // Arrange
        $signature = 'user:create {--role} {--active}';
        $query = 'user:create --role --active';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertTrue($result->options->first()->value);
        $this->assertTrue($result->options->last()->value);
    }

    public function test_parse_with_arguments_and_options(): void
    {
        // Arrange
        $signature = 'user:create {name} {--role} {--active}';
        $query = 'user:create John --role --active';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertSame('John', $result->required->first()->value);
        $this->assertTrue($result->options->first()->value);
        $this->assertTrue($result->options->last()->value);
    }

    public function test_parse_with_options_between_arguments(): void
    {
        // Arrange
        $signature = 'user:create {name} {email} {--active} {--verbose}';
        $query = 'user:create John john@example.com --active';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertSame('John', $result->required->first()->value);
        $this->assertSame('john@example.com', $result->required->last()->value);
        $this->assertTrue($result->options->first()->value);  // active
        $this->assertFalse($result->options->last()->value);  // verbose
    }

    public function test_parse_with_variadic_argument(): void
    {
        // Arrange
        $signature = 'process {files*}';
        $query = 'process [file1.txt, file2.txt, file3.txt]';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $variadic = $result->variadic->first();
        $this->assertSame('files', $variadic->name);
        $this->assertCount(3, $variadic->values);
        $this->assertSame(['file1.txt', 'file2.txt', 'file3.txt'], $variadic->values->toArray());
    }

    public function test_parse_with_required_and_variadic_arguments(): void
    {
        // Arrange
        $signature = 'process {name} {files*}';
        $query = 'process John^Doe [file1.txt, file2.txt]';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertSame('John Doe', $result->required->first()->value);
        $variadic = $result->variadic->first();
        $this->assertSame('files', $variadic->name);
        $this->assertCount(2, $variadic->values);
        $this->assertSame(['file1.txt', 'file2.txt'], $variadic->values->toArray());
    }

    public function test_parse_with_variadic_and_options(): void
    {
        // Arrange
        $signature = 'process {files*} {--verbose}';
        $query = 'process [file1.txt, file2.txt] --verbose';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $variadic = $result->variadic->first();
        $this->assertCount(2, $variadic->values);
        $this->assertTrue($result->options->first()->value);
    }

    public function test_parse_with_empty_query(): void
    {
        // Arrange
        $signature = 'test:cmd';
        $query = 'test:cmd';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertSame('test:cmd', $result->source);
        $this->assertCount(0, $result->required);
        $this->assertCount(0, $result->default);
        $this->assertCount(0, $result->variadic);
        $this->assertCount(0, $result->options);
    }

    public function test_parse_with_complex_command(): void
    {
        // Arrange
        $signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';
        $query = 'backup /var/www /backup tar.gz [cache, logs, tmp] [home, data, models] --force';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertSame('backup', $result->source);
        $this->assertSame('/var/www', $result->required->first()->value);
        $this->assertSame('/backup', $result->required->last()->value);
        $this->assertSame('tar.gz', $result->default->first()->value);
        $this->assertSame('dist', $result->default->last()->value);

        $excludes = $result->variadic->first();
        $this->assertSame('excludes', $excludes->name);
        $this->assertSame(['cache', 'logs', 'tmp'], $excludes->values->toArray());

        $purpose = $result->variadic->last();
        $this->assertSame('purpose', $purpose->name);
        $this->assertSame(['home', 'data', 'models'], $purpose->values->toArray());

        $this->assertTrue($result->options->first()->value);
        $this->assertFalse($result->options->last()->value);
    }
}
