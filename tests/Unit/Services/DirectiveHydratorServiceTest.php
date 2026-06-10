<?php
// tests/Unit/Services/DirectiveHydratorServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestVariadicDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
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

        $this->factory = $this->createMock(DirectiveFactoryInterface::class);
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->service = new DirectiveHydratorService($this->factory);
    }

    private function createTestDirective(): TestDirective
    {
        return new TestDirective($this->interaction);
    }

    private function createTestVariadicDirective(): TestVariadicDirective
    {
        return new TestVariadicDirective($this->interaction);
    }

    private function createParsedArgumentCollection(array $items): ParsedArgumentCollection
    {
        $collection = new ParsedArgumentCollection();
        for ($i = 0; $i < count($items); $i += 2) {
            if (isset($items[$i + 1])) {
                $collection->addArgument($items[$i + 1], $items[$i]);
            }
        }
        return $collection;
    }

    private function createParsedOptionCollection(array $items): ParsedOptionCollection
    {
        $collection = new ParsedOptionCollection();
        for ($i = 0; $i < count($items); $i += 2) {
            if (isset($items[$i])) {
                $value = $items[$i + 1] ?? 'true';
                $isFlag = $value === 'true';
                $collection->addOption($items[$i], $value, $isFlag);
            }
        }
        return $collection;
    }

    private function createEmptyParsedDirectiveRecord(): ParsedDirectiveRecord
    {
        return new ParsedDirectiveRecord(
            arguments: new ParsedArgumentCollection(),
            options: new ParsedOptionCollection(),
            variadic_arguments: new StringTypedCollection(),
        );
    }

    // ==================== Hydrate Method Tests ====================

    public function test_hydrate_calls_factory_and_sets_arguments(): void
    {
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestDirective::class)
            ->willReturn($directive);

        $arguments = $this->createParsedArgumentCollection(['John Doe', 'name', 'john@example.com', 'email']);
        $options = new ParsedOptionCollection();
        $variadicArguments = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
            variadic_arguments: $variadicArguments,
        );

        $result = $this->service->hydrate(TestDirective::class, $parsed);

        $this->assertSame('John Doe', $result->argument('name'));
        $this->assertSame('john@example.com', $result->argument('email'));
    }

    public function test_hydrate_sets_options_with_boolean_normalization(): void
    {
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestDirective::class)
            ->willReturn($directive);

        $arguments = new ParsedArgumentCollection();
        $options = $this->createParsedOptionCollection(['active', 'true', 'verbose', 'true', 'role', 'admin']);
        $variadicArguments = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
            variadic_arguments: $variadicArguments,
        );

        $result = $this->service->hydrate(TestDirective::class, $parsed);

        $this->assertTrue($result->option('active'));
        $this->assertTrue($result->option('verbose'));
        $this->assertSame('admin', $result->option('role'));
    }

    public function test_hydrate_sets_variadic_arguments(): void
    {
        $directive = $this->createTestVariadicDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestVariadicDirective::class)
            ->willReturn($directive);

        $arguments = $this->createParsedArgumentCollection(['John Doe', 'name']);
        $options = new ParsedOptionCollection();

        $variadicArguments = new StringTypedCollection();
        $variadicArguments->add('file1.txt');
        $variadicArguments->add('file2.txt');
        $variadicArguments->add('file3.txt');

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
            variadic_arguments: $variadicArguments,
        );

        $result = $this->service->hydrate(TestVariadicDirective::class, $parsed);

        $this->assertSame('John Doe', $result->argument('name'));
        $this->assertTrue($result->hasVariadicArguments());
        $this->assertEquals(3, $result->getVariadicArguments()->count());
        $this->assertTrue($result->getVariadicArguments()->contains('file1.txt'));
        $this->assertTrue($result->getVariadicArguments()->contains('file2.txt'));
        $this->assertTrue($result->getVariadicArguments()->contains('file3.txt'));
    }

    public function test_hydrate_handles_multiple_arguments(): void
    {
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = $this->createParsedArgumentCollection(['value1', 'key1', 'value2', 'key2', 'value3', 'key3']);
        $options = new ParsedOptionCollection();
        $variadicArguments = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
            variadic_arguments: $variadicArguments,
        );

        $result = $this->service->hydrate(TestDirective::class, $parsed);

        $this->assertSame('value1', $result->argument('key1'));
        $this->assertSame('value2', $result->argument('key2'));
        $this->assertSame('value3', $result->argument('key3'));
    }

    public function test_hydrate_handles_empty_arguments(): void
    {
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $parsed = $this->createEmptyParsedDirectiveRecord();

        $result = $this->service->hydrate(TestDirective::class, $parsed);

        $this->assertNull($result->argument('any'));
        $this->assertNull($result->option('any'));
        $this->assertTrue($result->getVariadicArguments()->isEmpty());
    }

    public function test_hydrate_handles_incomplete_argument_pair(): void
    {
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = $this->createParsedArgumentCollection(['value1', 'key1']);
        $options = new ParsedOptionCollection();
        $variadicArguments = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
            variadic_arguments: $variadicArguments,
        );

        $result = $this->service->hydrate(TestDirective::class, $parsed);

        $this->assertSame('value1', $result->argument('key1'));
    }

    public function test_hydrate_handles_incomplete_option_pair(): void
    {
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = new ParsedArgumentCollection();
        $options = $this->createParsedOptionCollection(['active', 'true']);
        $variadicArguments = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
            variadic_arguments: $variadicArguments,
        );

        $result = $this->service->hydrate(TestDirective::class, $parsed);

        $this->assertTrue($result->option('active'));
    }

    public function test_hydrate_handles_options_with_various_values(): void
    {
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = new ParsedArgumentCollection();
        $options = $this->createParsedOptionCollection([
            'string_value',
            'hello',
            'true_value',
            'true',
            'false_value',
            'false',
            'null_value',
            'true',
            'empty_value',
            'true',
            'numeric_value',
            '42',
        ]);
        $variadicArguments = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
            variadic_arguments: $variadicArguments,
        );

        $result = $this->service->hydrate(TestDirective::class, $parsed);

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
        $blueprint = $this->service->hydrateBlueprint(TestDirective::class);

        $this->assertSame(TestDirective::class, $blueprint->class);
        $this->assertSame('test-directive', $blueprint->signature);
        $this->assertSame('Test directive', $blueprint->description);
    }

    // ==================== Hydrate For Aliases Tests ====================

    public function test_hydrate_for_aliases_returns_directive_instance(): void
    {
        $result = $this->service->hydrateForAliases(TestDirective::class);

        $this->assertInstanceOf(TestDirective::class, $result);
        $this->assertSame('test-directive', $result->getSignature());
        $this->assertSame('Test directive', $result->getDescription());
    }

    // ==================== Hydrate With Variadic Only Tests ====================

    public function test_hydrate_with_only_variadic_arguments(): void
    {
        $directive = $this->createTestVariadicDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestVariadicDirective::class)
            ->willReturn($directive);

        $arguments = $this->createParsedArgumentCollection(['John Doe', 'name']);
        $options = new ParsedOptionCollection();

        $variadicArguments = new StringTypedCollection();
        $variadicArguments->add('a.txt');
        $variadicArguments->add('b.txt');
        $variadicArguments->add('c.txt');

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
            variadic_arguments: $variadicArguments,
        );

        $result = $this->service->hydrate(TestVariadicDirective::class, $parsed);

        $this->assertEquals(3, $result->getVariadicArguments()->count());
        $this->assertTrue($result->hasVariadicArguments());
        $this->assertTrue($result->getVariadicArguments()->contains('a.txt'));
        $this->assertTrue($result->getVariadicArguments()->contains('b.txt'));
        $this->assertTrue($result->getVariadicArguments()->contains('c.txt'));
    }

    public function test_hydrate_with_mixed_arguments_and_variadic(): void
    {
        $directive = $this->createTestVariadicDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestVariadicDirective::class)
            ->willReturn($directive);

        $arguments = $this->createParsedArgumentCollection(['John Doe', 'name']);
        $options = $this->createParsedOptionCollection(['verbose', 'true']);

        $variadicArguments = new StringTypedCollection();
        $variadicArguments->add('file1.txt');
        $variadicArguments->add('file2.txt');

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
            variadic_arguments: $variadicArguments,
        );

        $result = $this->service->hydrate(TestVariadicDirective::class, $parsed);

        $this->assertSame('John Doe', $result->argument('name'));
        $this->assertTrue($result->option('verbose'));
        $this->assertEquals(2, $result->getVariadicArguments()->count());
        $this->assertTrue($result->getVariadicArguments()->contains('file1.txt'));
        $this->assertTrue($result->getVariadicArguments()->contains('file2.txt'));
    }

    public function test_hydrate_with_empty_variadic_arguments(): void
    {
        $directive = $this->createTestVariadicDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestVariadicDirective::class)
            ->willReturn($directive);

        $arguments = $this->createParsedArgumentCollection(['John Doe', 'name']);
        $options = new ParsedOptionCollection();
        $variadicArguments = new StringTypedCollection();

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
            variadic_arguments: $variadicArguments,
        );

        $result = $this->service->hydrate(TestVariadicDirective::class, $parsed);

        $this->assertSame('John Doe', $result->argument('name'));
        $this->assertTrue($result->getVariadicArguments()->isEmpty());
        $this->assertFalse($result->hasVariadicArguments());
    }
}
