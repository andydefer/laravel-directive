<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Testing;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Testing\ClosureDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class ClosureDirectiveTest extends UnitTestCase
{
    private DirectiveInteractionService&MockObject $interaction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
    }

    private function createDirective(string $signature, callable $execute): ClosureDirective
    {
        return new ClosureDirective(
            signature: $signature,
            execute: $execute(...),
            interaction: $this->interaction,
        );
    }

    public function test_returns_custom_signature(): void
    {
        // Arrange
        $signature = 'test-custom-signature';

        // Act
        $directive = $this->createDirective($signature, fn($d) => ExitCode::SUCCESS);

        // Assert
        $this->assertSame($signature, $directive->getSignature());
    }

    public function test_returns_test_description(): void
    {
        // Act
        $directive = $this->createDirective('test', fn($d) => ExitCode::SUCCESS);

        // Assert
        $this->assertSame('Test directive created from closure', $directive->getDescription());
    }

    public function test_executes_closure_and_returns_exit_code(): void
    {
        // Arrange
        $executed = false;

        $directive = $this->createDirective('test', function ($d) use (&$executed) {
            $executed = true;
            return ExitCode::SUCCESS;
        });

        // Act
        $result = $directive->execute();

        // Assert
        $this->assertTrue($executed);
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_passes_directive_instance_to_closure(): void
    {
        // Arrange
        $receivedDirective = null;

        $directive = $this->createDirective('test', function ($d) use (&$receivedDirective) {
            $receivedDirective = $d;
            return ExitCode::SUCCESS;
        });

        // Act
        $directive->execute();

        // Assert
        $this->assertSame($directive, $receivedDirective);
    }

    public function test_can_return_failure(): void
    {
        // Arrange
        $directive = $this->createDirective('test', fn($d) => ExitCode::FAILURE);

        // Act
        $result = $directive->execute();

        // Assert
        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_can_return_invalid_argument(): void
    {
        // Arrange
        $directive = $this->createDirective('test', fn($d) => ExitCode::INVALID_ARGUMENT);

        // Act
        $result = $directive->execute();

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_can_access_arguments(): void
    {
        // Arrange
        $directive = $this->createDirective('test {name}', function ($d) {
            $name = $d->argument('name');
            return $name === 'John' ? ExitCode::SUCCESS : ExitCode::FAILURE;
        });

        $this->setArguments($directive, ['name' => 'John']);

        // Act
        $result = $directive->execute();

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_can_access_options(): void
    {
        // Arrange
        $directive = $this->createDirective('test {--verbose}', function ($d) {
            return $d->hasOption('verbose') ? ExitCode::SUCCESS : ExitCode::FAILURE;
        });

        $this->setOptions($directive, ['verbose' => true]);

        // Act
        $result = $directive->execute();

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_can_output_line_messages(): void
    {
        // Assert
        $this->interaction->expects($this->once())
            ->method('line')
            ->with('Hello World');

        // Arrange
        $directive = $this->createDirective('test', function ($d) {
            $d->line('Hello World');
            return ExitCode::SUCCESS;
        });

        // Act
        $directive->execute();
    }

    public function test_can_output_info_messages(): void
    {
        // Assert
        $this->interaction->expects($this->once())
            ->method('info')
            ->with('Information message');

        // Arrange
        $directive = $this->createDirective('test', function ($d) {
            $d->info('Information message');
            return ExitCode::SUCCESS;
        });

        // Act
        $directive->execute();
    }

    public function test_can_output_error_messages(): void
    {
        // Assert
        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Error message');

        // Arrange
        $directive = $this->createDirective('test', function ($d) {
            $d->error('Error message');
            return ExitCode::SUCCESS;
        });

        // Act
        $directive->execute();
    }

    public function test_multiple_directives_can_be_created(): void
    {
        // Act
        $firstDirective = $this->createDirective('first', fn($d) => ExitCode::SUCCESS);
        $secondDirective = $this->createDirective('second', fn($d) => ExitCode::SUCCESS);

        // Assert
        $this->assertSame('first', $firstDirective->getSignature());
        $this->assertSame('second', $secondDirective->getSignature());
    }

    /**
     * Set arguments on a directive via reflection for testing.
     */
    private function setArguments(ClosureDirective $directive, array $arguments): void
    {
        $collection = new ParameterCollection();
        foreach ($arguments as $name => $value) {
            $collection->add(new ParameterRecord(name: $name, value: $value));
        }

        $reflection = new \ReflectionClass($directive);
        $property = $reflection->getProperty('arguments');
        $property->setValue($directive, $collection);
    }

    /**
     * Set options on a directive via reflection for testing.
     */
    private function setOptions(ClosureDirective $directive, array $options): void
    {
        $collection = new ParameterCollection();
        foreach ($options as $name => $value) {
            $collection->add(new ParameterRecord(name: $name, value: $value));
        }

        $reflection = new \ReflectionClass($directive);
        $property = $reflection->getProperty('options');
        $property->setValue($directive, $collection);
    }
}
