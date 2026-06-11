<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Testing;

use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\PrimitiveTypeConverterService;
use AndyDefer\Directive\Testing\ClosureDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class ClosureDirectiveTest extends UnitTestCase
{
    private DirectiveInteractionService&MockObject $interaction;

    private LaravelBootstrapperContext $laravelBootstrapperContext;

    private PrimitiveTypeConverterService $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->laravelBootstrapperContext = new LaravelBootstrapperContext;
        $this->converter = new PrimitiveTypeConverterService();
    }

    private function createDirective(string $signature, callable $execute): ClosureDirective
    {
        $context = new DirectiveContext(
            laravelBootstrapper: $this->laravelBootstrapperContext,
            blueprint: new DirectiveBlueprintRecord(ClosureDirective::class, $signature, 'Test directive created from closure'),
            aliases: new StringTypedCollection,
            shouldBootLaravel: false,
        );

        return new ClosureDirective(
            context: $context,
            interaction: $this->interaction,
            signature: $signature,
            execute: $execute,
        );
    }

    public function test_returns_custom_signature(): void
    {
        $signature = 'test-custom-signature';
        $directive = $this->createDirective($signature, fn($d) => ExitCode::SUCCESS);

        $this->assertSame($signature, $directive->getSignature());
    }

    public function test_returns_test_description(): void
    {
        $directive = $this->createDirective('test', fn($d) => ExitCode::SUCCESS);

        $this->assertSame('Test directive created from closure', $directive->getDescription());
    }

    public function test_executes_closure_and_returns_exit_code(): void
    {
        $executed = false;

        $directive = $this->createDirective('test', function ($d) use (&$executed) {
            $executed = true;

            return ExitCode::SUCCESS;
        });

        $result = $directive->execute();

        $this->assertTrue($executed);
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_passes_directive_instance_to_closure(): void
    {
        $receivedDirective = null;

        $directive = $this->createDirective('test', function ($d) use (&$receivedDirective) {
            $receivedDirective = $d;

            return ExitCode::SUCCESS;
        });

        $directive->execute();

        $this->assertSame($directive, $receivedDirective);
    }

    public function test_can_return_failure(): void
    {
        $directive = $this->createDirective('test', fn($d) => ExitCode::FAILURE);
        $result = $directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_can_return_invalid_argument(): void
    {
        $directive = $this->createDirective('test', fn($d) => ExitCode::INVALID_ARGUMENT);
        $result = $directive->execute();

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_can_access_arguments(): void
    {
        $directive = $this->createDirective('test {name}', function ($d) {
            $name = $d->argument('name');

            return $name === 'John' ? ExitCode::SUCCESS : ExitCode::FAILURE;
        });

        $this->setArguments($directive, ['name' => 'John']);

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_can_access_options(): void
    {
        $directive = $this->createDirective('test {--verbose}', function ($d) {
            return $d->hasOption('verbose') ? ExitCode::SUCCESS : ExitCode::FAILURE;
        });

        $this->setOptions($directive, ['verbose' => true]);

        $result = $directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_can_output_line_messages(): void
    {
        $this->interaction->expects($this->once())
            ->method('line')
            ->with('Hello World');

        $directive = $this->createDirective('test', function ($d) {
            $d->line('Hello World');

            return ExitCode::SUCCESS;
        });

        $directive->execute();
    }

    public function test_can_output_info_messages(): void
    {
        $this->interaction->expects($this->once())
            ->method('info')
            ->with('Information message');

        $directive = $this->createDirective('test', function ($d) {
            $d->info('Information message');

            return ExitCode::SUCCESS;
        });

        $directive->execute();
    }

    public function test_can_output_error_messages(): void
    {
        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Error message');

        $directive = $this->createDirective('test', function ($d) {
            $d->error('Error message');

            return ExitCode::SUCCESS;
        });

        $directive->execute();
    }

    public function test_multiple_directives_can_be_created(): void
    {
        $firstDirective = $this->createDirective('first', fn($d) => ExitCode::SUCCESS);
        $secondDirective = $this->createDirective('second', fn($d) => ExitCode::SUCCESS);

        $this->assertSame('first', $firstDirective->getSignature());
        $this->assertSame('second', $secondDirective->getSignature());
    }

    private function setArguments(ClosureDirective $directive, array $arguments): void
    {
        $collection = new ParameterVOCollection;

        foreach ($arguments as $name => $value) {
            $type = $this->converter->detectType($value);
            $collection->add(new ParameterVO(name: $name, value: $value, type: $type));
        }

        $reflection = new \ReflectionClass($directive);
        $property = $reflection->getProperty('context');
        $context = $property->getValue($directive);
        $context->setArguments($collection);
    }

    private function setOptions(ClosureDirective $directive, array $options): void
    {
        $collection = new ParameterVOCollection;

        foreach ($options as $name => $value) {
            $type = $this->converter->detectType($value);
            $collection->add(new ParameterVO(name: $name, value: $value, type: $type));
        }

        $reflection = new \ReflectionClass($directive);
        $property = $reflection->getProperty('context');
        $context = $property->getValue($directive);
        $context->setOptions($collection);
    }
}
