<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\ScalarTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveHydratorServiceTest extends UnitTestCase
{
    private DirectiveFactoryInterface&MockObject $factory;
    private DirectiveInteractionService&MockObject $interaction;
    private DirectiveHydratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create mocked dependencies
        $this->factory = $this->createMock(DirectiveFactoryInterface::class);
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->service = new DirectiveHydratorService($this->factory);
    }

    /**
     * Creates a test directive instance.
     *
     * @return TestDirective
     */
    private function createTestDirective(): TestDirective
    {
        return new TestDirective($this->interaction);
    }

    /**
     * Creates a scalar typed collection from an array of items.
     *
     * @param array<mixed> $items
     *
     * @return ScalarTypedCollection
     */
    private function createScalarCollection(array $items): ScalarTypedCollection
    {
        $collection = new ScalarTypedCollection();
        foreach ($items as $item) {
            $collection->add($item);
        }

        return $collection;
    }

    // ==================== Hydrate Method Tests ====================

    public function test_hydrate_calls_factory_and_sets_arguments(): void
    {
        // Arrange: Create directive and set up expectations
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestDirective::class)
            ->willReturn($directive);

        $arguments = $this->createScalarCollection(['John Doe', 'name', 'john@example.com', 'email']);
        $options = new ScalarTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act: Hydrate the directive
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert: Verify arguments were set correctly
        $this->assertSame('John Doe', $result->argument('name'));
        $this->assertSame('john@example.com', $result->argument('email'));
    }

    public function test_hydrate_sets_options_with_boolean_normalization(): void
    {
        // Arrange: Create directive and set up expectations
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestDirective::class)
            ->willReturn($directive);

        $arguments = new ScalarTypedCollection();
        $options = $this->createScalarCollection(['active', 'true', 'verbose', null, 'role', 'admin']);

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act: Hydrate the directive
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert: Verify options were normalized correctly
        $this->assertTrue($result->option('active'));
        $this->assertTrue($result->option('verbose'));
        $this->assertSame('admin', $result->option('role'));
    }

    public function test_hydrate_handles_multiple_arguments(): void
    {
        // Arrange: Create directive and set up expectations
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = $this->createScalarCollection(['value1', 'key1', 'value2', 'key2', 'value3', 'key3']);
        $options = new ScalarTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act: Hydrate the directive
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert: Verify all arguments were set correctly
        $this->assertSame('value1', $result->argument('key1'));
        $this->assertSame('value2', $result->argument('key2'));
        $this->assertSame('value3', $result->argument('key3'));
    }

    public function test_hydrate_handles_empty_arguments(): void
    {
        // Arrange: Create directive and set up expectations
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = new ScalarTypedCollection();
        $options = new ScalarTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act: Hydrate the directive
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert: Verify no arguments or options are present
        $this->assertNull($result->argument('any'));
        $this->assertNull($result->option('any'));
    }

    public function test_hydrate_handles_incomplete_argument_pair(): void
    {
        // Arrange: Create directive and set up expectations
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = $this->createScalarCollection(['value1', 'key1', 'value2']);
        $options = new ScalarTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act: Hydrate the directive
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert: Verify complete pair is set, incomplete is ignored
        $this->assertSame('value1', $result->argument('key1'));
    }

    public function test_hydrate_handles_incomplete_option_pair(): void
    {
        // Arrange: Create directive and set up expectations
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = new ScalarTypedCollection();
        $options = $this->createScalarCollection(['active', 'true', 'verbose']);

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        // Act: Hydrate the directive
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert: Verify complete option pair is set
        $this->assertTrue($result->option('active'));
    }

    public function test_hydrate_handles_options_with_various_values(): void
    {
        // Arrange: Create directive and set up expectations
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = new ScalarTypedCollection();
        $options = $this->createScalarCollection([
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

        // Act: Hydrate the directive
        $result = $this->service->hydrate(TestDirective::class, $parsed);

        // Assert: Verify all option types are handled correctly
        $this->assertSame('hello', $result->option('string_value'));
        $this->assertTrue($result->option('true_value'));
        $this->assertFalse($result->option('false_value'));
        $this->assertTrue($result->option('null_value'));
        $this->assertTrue($result->option('empty_value'));
        $this->assertSame('42', $result->option('numeric_value'));
    }

    // ==================== Hydrate Blueprint Tests ====================

    public function test_hydrate_blueprint_returns_blueprint_record(): void
    {
        // Act: Hydrate only the blueprint
        $blueprint = $this->service->hydrateBlueprint(TestDirective::class);

        // Assert: Verify blueprint contains correct data
        $this->assertSame(TestDirective::class, $blueprint->class);
        $this->assertSame('test-directive', $blueprint->signature);
        $this->assertSame('Test directive', $blueprint->description);
    }

    // ==================== Hydrate For Aliases Tests ====================

    public function test_hydrate_for_aliases_returns_directive_instance(): void
    {
        // Act: Hydrate directive for aliases only
        $result = $this->service->hydrateForAliases(TestDirective::class);

        // Assert: Verify directive instance has correct metadata
        $this->assertInstanceOf(TestDirective::class, $result);
        $this->assertSame('test-directive', $result->getSignature());
        $this->assertSame('Test directive', $result->getDescription());
    }
}
