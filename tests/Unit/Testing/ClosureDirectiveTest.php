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
    private ClosureDirective $directive;

    private DirectiveInteractionService&MockObject $interaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interaction = $this->createMock(DirectiveInteractionService::class);
    }

    public function test_closure_directive_returns_custom_signature(): void
    {
        $signature = 'test-custom-signature';

        $this->directive = new ClosureDirective(
            signature: $signature,
            execute: fn ($d) => ExitCode::SUCCESS,
            interaction: $this->interaction,
        );

        $this->assertSame($signature, $this->directive->getSignature());
    }

    public function test_closure_directive_returns_test_description(): void
    {
        $this->directive = new ClosureDirective(
            signature: 'test',
            execute: fn ($d) => ExitCode::SUCCESS,
            interaction: $this->interaction,
        );

        $this->assertSame('Test directive created from closure', $this->directive->getDescription());
    }

    public function test_closure_directive_executes_closure_and_returns_exit_code(): void
    {
        $executed = false;

        $this->directive = new ClosureDirective(
            signature: 'test',
            execute: function ($d) use (&$executed) {
                $executed = true;

                return ExitCode::SUCCESS;
            },
            interaction: $this->interaction,
        );

        $result = $this->directive->execute();

        $this->assertTrue($executed);
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_closure_directive_passes_directive_instance_to_closure(): void
    {
        $receivedDirective = null;

        $this->directive = new ClosureDirective(
            signature: 'test',
            execute: function ($d) use (&$receivedDirective) {
                $receivedDirective = $d;

                return ExitCode::SUCCESS;
            },
            interaction: $this->interaction,
        );

        $this->directive->execute();

        $this->assertSame($this->directive, $receivedDirective);
    }

    public function test_closure_directive_can_return_failure(): void
    {
        $this->directive = new ClosureDirective(
            signature: 'test',
            execute: fn ($d) => ExitCode::FAILURE,
            interaction: $this->interaction,
        );

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_closure_directive_can_return_invalid_argument(): void
    {
        $this->directive = new ClosureDirective(
            signature: 'test',
            execute: fn ($d) => ExitCode::INVALID_ARGUMENT,
            interaction: $this->interaction,
        );

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_closure_directive_can_access_arguments(): void
    {
        $this->directive = new ClosureDirective(
            signature: 'test {name}',
            execute: function ($d) {
                $name = $d->argument('name');

                return $name === 'John' ? ExitCode::SUCCESS : ExitCode::FAILURE;
            },
            interaction: $this->interaction,
        );

        // Set arguments via reflection for testing
        $reflection = new \ReflectionClass($this->directive);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'John'));
        $property->setValue($this->directive, $arguments);

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_closure_directive_can_access_options(): void
    {
        $this->directive = new ClosureDirective(
            signature: 'test {--verbose}',
            execute: function ($d) {
                $hasVerbose = $d->hasOption('verbose');

                return $hasVerbose ? ExitCode::SUCCESS : ExitCode::FAILURE;
            },
            interaction: $this->interaction,
        );

        $reflection = new \ReflectionClass($this->directive);
        $optionsProperty = $reflection->getProperty('options');

        $options = new ParameterCollection;
        $options->add(new ParameterRecord(name: 'verbose', value: true));
        $optionsProperty->setValue($this->directive, $options);

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_closure_directive_can_output_messages(): void
    {
        $this->interaction->expects($this->once())
            ->method('line')
            ->with('Hello World');

        $this->directive = new ClosureDirective(
            signature: 'test',
            execute: function ($d) {
                $d->line('Hello World');

                return ExitCode::SUCCESS;
            },
            interaction: $this->interaction,
        );

        $this->directive->execute();
    }

    public function test_closure_directive_can_output_info_messages(): void
    {
        $this->interaction->expects($this->once())
            ->method('info')
            ->with('Information message');

        $this->directive = new ClosureDirective(
            signature: 'test',
            execute: function ($d) {
                $d->info('Information message');

                return ExitCode::SUCCESS;
            },
            interaction: $this->interaction,
        );

        $this->directive->execute();
    }

    public function test_closure_directive_can_output_error_messages(): void
    {
        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Error message');

        $this->directive = new ClosureDirective(
            signature: 'test',
            execute: function ($d) {
                $d->error('Error message');

                return ExitCode::SUCCESS;
            },
            interaction: $this->interaction,
        );

        $this->directive->execute();
    }

    public function test_multiple_closure_directives_can_be_created(): void
    {
        $directive1 = new ClosureDirective(
            signature: 'first',
            execute: fn ($d) => ExitCode::SUCCESS,
            interaction: $this->interaction,
        );

        $directive2 = new ClosureDirective(
            signature: 'second',
            execute: fn ($d) => ExitCode::SUCCESS,
            interaction: $this->interaction,
        );

        $this->assertSame('first', $directive1->getSignature());
        $this->assertSame('second', $directive2->getSignature());
    }
}
