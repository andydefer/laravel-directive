<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestDirective;
use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveHydratorServiceTest extends TestCase
{
    private DirectiveFactoryInterface&MockObject $factory;
    private DirectiveInteractionService&MockObject $interaction;
    private DirectiveHydratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createMock(DirectiveFactoryInterface::class);
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->service = new DirectiveHydratorService($this->factory);
    }

    private function createTestDirective(): TestDirective
    {
        return new TestDirective($this->interaction);
    }

    /**
     * Create a collection that accepts strings and null values.
     */
    private function createMixedCollection(array $items): TypedCollection
    {
        $collection = new TypedCollection('string', 'null');
        foreach ($items as $item) {
            $collection->add($item);
        }

        return $collection;
    }

    public function test_hydrate_calls_factory_and_sets_arguments(): void
    {
        // Arrange
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestDirective::class)
            ->willReturn($directive);

        $arguments = $this->createMixedCollection(['John Doe', 'name', 'john@example.com', 'email']);
        $options = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert
        $this->assertSame('John Doe', $result->argument('name'));
        $this->assertSame('john@example.com', $result->argument('email'));
    }

    public function test_hydrate_sets_options_with_boolean_normalization(): void
    {
        // Arrange
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestDirective::class)
            ->willReturn($directive);

        $arguments = new StringTypedCollection();
        $options = $this->createMixedCollection(['active', 'true', 'verbose', null, 'role', 'admin']);

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert
        $this->assertTrue($result->option('active'));
        $this->assertTrue($result->option('verbose'));
        $this->assertSame('admin', $result->option('role'));
    }

    public function test_hydrate_handles_multiple_arguments(): void
    {
        // Arrange
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = $this->createMixedCollection(['value1', 'key1', 'value2', 'key2', 'value3', 'key3']);
        $options = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert
        $this->assertSame('value1', $result->argument('key1'));
        $this->assertSame('value2', $result->argument('key2'));
        $this->assertSame('value3', $result->argument('key3'));
    }

    public function test_hydrate_handles_empty_arguments(): void
    {
        // Arrange
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = new StringTypedCollection();
        $options = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert
        $this->assertNull($result->argument('any'));
        $this->assertNull($result->option('any'));
    }

    public function test_hydrate_handles_incomplete_argument_pair(): void
    {
        // Arrange
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = $this->createMixedCollection(['value1', 'key1', 'value2']);
        $options = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert
        $this->assertSame('value1', $result->argument('key1'));
    }

    public function test_hydrate_handles_incomplete_option_pair(): void
    {
        // Arrange
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = new StringTypedCollection();
        $options = $this->createMixedCollection(['active', 'true', 'verbose']);

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert
        $this->assertTrue($result->option('active'));
    }

    public function test_hydrate_handles_options_with_various_values(): void
    {
        // Arrange
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = new StringTypedCollection();
        $options = $this->createMixedCollection([
            'string_value',
            'hello',
            'true_value',
            'true',
            'false_value',
            'false',
            'null_value',
            null,
            'empty_value',
            '',
            'numeric_value',
            '42',
        ]);

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert
        $this->assertSame('hello', $result->option('string_value'));
        $this->assertTrue($result->option('true_value'));
        $this->assertFalse($result->option('false_value'));
        $this->assertTrue($result->option('null_value'));
        $this->assertTrue($result->option('empty_value'));
        $this->assertSame('42', $result->option('numeric_value'));
    }

    public function test_hydrate_blueprint_returns_blueprint_record(): void
    {
        // Arrange
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestDirective::class)
            ->willReturn($directive);

        // Act
        $blueprint = $this->service->hydrateBlueprint(TestDirective::class);

        // Assert
        $this->assertSame(TestDirective::class, $blueprint->class);
        $this->assertSame('test-directive', $blueprint->signature);
        $this->assertSame('Test directive', $blueprint->description);
    }

    public function test_hydrate_for_aliases_returns_directive_instance(): void
    {
        // Arrange
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestDirective::class)
            ->willReturn($directive);

        // Act
        $result = $this->service->hydrateForAliases(TestDirective::class);

        // Assert
        $this->assertSame($directive, $result);
    }
}
