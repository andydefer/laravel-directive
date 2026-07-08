<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Services;

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
        $this->assertCount(0, $result->flags);
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

    public function test_parse_with_flags(): void
    {
        // Arrange
        $signature = 'user:create {--admin} {--active}';
        $query = 'user:create --admin --active';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertTrue($result->flags->first()->value);
        $this->assertTrue($result->flags->last()->value);
    }

    public function test_parse_with_arguments_and_flags(): void
    {
        // Arrange
        $signature = 'user:create {name} {--admin} {--active}';
        $query = 'user:create John --admin --active';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertSame('John', $result->required->first()->value);
        $this->assertTrue($result->flags->first()->value);
        $this->assertTrue($result->flags->last()->value);
    }

    public function test_parse_with_flags_between_arguments(): void
    {
        // Arrange
        $signature = 'user:create {name} {email} {--active} {--verbose}';
        $query = 'user:create John john@example.com --active';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $this->assertSame('John', $result->required->first()->value);
        $this->assertSame('john@example.com', $result->required->last()->value);
        $this->assertTrue($result->flags->first()->value);  // active
        $this->assertFalse($result->flags->last()->value);  // verbose
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

    public function test_parse_with_variadic_and_flags(): void
    {
        // Arrange
        $signature = 'process {files*} {--verbose}';
        $query = 'process [file1.txt, file2.txt] --verbose';

        // Act
        $result = $this->service->parse($signature, $query);

        // Assert
        $variadic = $result->variadic->first();
        $this->assertCount(2, $variadic->values);
        $this->assertTrue($result->flags->first()->value);
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
        $this->assertCount(0, $result->flags);
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

        $this->assertTrue($result->flags->first()->value);
        $this->assertFalse($result->flags->last()->value);
    }

    public function test_validate_query(): void
    {
        // Arrange
        $signature = 'backup {source} {destination}';
        $query = 'backup /var/www';

        // Act
        $result = $this->service->validate($signature, $query);

        // Assert
        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
    }

    public function test_is_valid_query(): void
    {
        // Arrange
        $signature = 'backup {source} {destination}';
        $query = 'backup /var/www /backup';

        // Act
        $result = $this->service->isValid($signature, $query);

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_valid_query_with_missing_argument(): void
    {
        // Arrange
        $signature = 'backup {source} {destination}';
        $query = 'backup /var/www';

        // Act
        $result = $this->service->isValid($signature, $query);

        // Assert
        $this->assertFalse($result);
    }

    public function test_validate_signature(): void
    {
        // Arrange
        $signature = 'backup {source} {destination} {--force}';

        // Act
        $result = $this->service->validateSignature($signature);

        // Assert
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_signature_invalid_order(): void
    {
        // Arrange
        $signature = 'backup {format=zip} {source} {--force}';

        // Act
        $result = $this->service->validateSignature($signature);

        // Assert
        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
    }

    public function test_is_signature_valid(): void
    {
        // Arrange
        $signature = 'backup {source} {destination} {--force}';

        // Act
        $result = $this->service->isSignatureValid($signature);

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_signature_valid_invalid(): void
    {
        // Arrange
        $signature = 'backup {format=zip} {source} {--force}';

        // Act
        $result = $this->service->isSignatureValid($signature);

        // Assert
        $this->assertFalse($result);
    }
}
