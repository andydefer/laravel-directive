<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Directives;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Directives\MakeDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Directive\Tasks\AskQuestionTask;
use AndyDefer\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\Directive\Tasks\DisplayMessageTask;
use AndyDefer\Directive\Tasks\DisplayTableTask;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class MakeDirectiveTest extends TestCase
{
    private MakeDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();

        $displayMessage = $this->createMock(DisplayMessageTask::class);
        $askQuestion = $this->createMock(AskQuestionTask::class);
        $confirmQuestion = $this->createMock(ConfirmQuestionTask::class);
        $displayTable = $this->createMock(DisplayTableTask::class);

        $this->directive = new MakeDirective(
            $displayMessage,
            $askQuestion,
            $confirmQuestion,
            $displayTable,
        );
    }

    public function test_get_signature_returns_make_directive(): void
    {
        // Arrange & Act
        $signature = $this->directive->getSignature();

        // Assert
        $this->assertSame('make:directive {name}', $signature);
    }

    public function test_get_description_returns_description(): void
    {
        // Arrange & Act
        $description = $this->directive->getDescription();

        // Assert
        $this->assertSame('Create a new directive class', $description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        // Arrange & Act
        $aliases = $this->directive->getAliases();

        // Assert
        $this->assertTrue($aliases->contains('create:directive'));
        $this->assertTrue($aliases->contains('make:cmd'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_execute_returns_error_when_name_missing(): void
    {
        // Act
        $result = $this->directive->execute();

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_generate_class_name_converts_signature_properly(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass(MakeDirective::class);
        $method = $reflection->getMethod('generateClassName');
        $method->setAccessible(true);

        // Act & Assert
        $this->assertSame('UserListDirective', $method->invoke($this->directive, 'user:list'));
        $this->assertSame('CacheClearDirective', $method->invoke($this->directive, 'cache:clear'));
        $this->assertSame('MyCustomCommandDirective', $method->invoke($this->directive, 'my-custom-command'));
    }

    public function test_generate_signature_adds_option_placeholder(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass(MakeDirective::class);
        $method = $reflection->getMethod('generateSignature');
        $method->setAccessible(true);

        // Act & Assert
        $this->assertSame('user:list {--option}', $method->invoke($this->directive, 'user:list'));
        $this->assertSame('cache:clear {--option}', $method->invoke($this->directive, 'cache:clear'));
    }

    public function test_generate_class_name_handles_complex_names(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass(MakeDirective::class);
        $method = $reflection->getMethod('generateClassName');
        $method->setAccessible(true);

        // Act & Assert
        $this->assertSame('UserCreateDirective', $method->invoke($this->directive, 'user:create'));
        $this->assertSame('UserDeleteForceDirective', $method->invoke($this->directive, 'user:delete-force'));
        $this->assertSame('UserGetByIdDirective', $method->invoke($this->directive, 'user:get-by-id'));
    }
}
