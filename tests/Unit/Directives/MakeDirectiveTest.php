<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Directives;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Directives\MakeDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\CreateDirectiveFileRecord;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Tasks\CreateDirectiveFileTask;
use AndyDefer\Directive\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class MakeDirectiveTest extends UnitTestCase
{
    private DirectiveInteractionService&MockObject $interaction;
    private SignatureValidationService&MockObject $signatureValidator;
    private DirectiveNamingService&MockObject $namingService;
    private CreateDirectiveFileTask&MockObject $fileTask;
    private MakeDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->signatureValidator = $this->createMock(SignatureValidationService::class);
        $this->namingService = $this->createMock(DirectiveNamingService::class);
        $this->fileTask = $this->createMock(CreateDirectiveFileTask::class);

        $this->directive = new MakeDirective(
            interaction: $this->interaction,
            signatureValidator: $this->signatureValidator,
            namingService: $this->namingService,
            fileTask: $this->fileTask,
        );
    }

    public function test_get_signature_returns_make_directive(): void
    {
        $signature = $this->directive->getSignature();
        $this->assertSame('make-directive {name}', $signature);
    }

    public function test_get_description_returns_description(): void
    {
        $description = $this->directive->getDescription();
        $this->assertSame('Create a new directive class', $description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $aliases = $this->directive->getAliases();
        $this->assertTrue($aliases->contains('create-directive'));
        $this->assertTrue($aliases->contains('make-cmd'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_execute_returns_error_when_name_missing(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');
        $property->setValue($this->directive, new ParameterCollection());

        $result = $this->directive->execute();
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_execute_validates_name_before_creation(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'name', value: 'user-create'));
        $property->setValue($this->directive, $arguments);

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user-create')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->namingService->expects($this->once())
            ->method('generateClassName')
            ->with('user-create')
            ->willReturn('UserCreateDirective');

        $this->fileTask->expects($this->once())
            ->method('execute')
            ->with('UserCreateDirective', 'user-create')
            ->willReturn(new CreateDirectiveFileRecord(
                success: true,
                path: '/app/Directives/UserCreateDirective.php',
                error: null,
            ));

        $this->interaction->expects($this->atLeastOnce())
            ->method('info')
            ->with('✅ Directive created successfully!');

        $result = $this->directive->execute();
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_error_when_name_invalid(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'name', value: 'user create'));
        $property->setValue($this->directive, $arguments);

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user create')
            ->willReturn(new ValidationResultRecord(
                isValid: false,
                error: 'Invalid directive name: "user create"'
            ));

        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Invalid directive name: "user create"');

        $result = $this->directive->execute();
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_execute_returns_error_when_file_creation_fails(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'name', value: 'user-create'));
        $property->setValue($this->directive, $arguments);  // Plus de setAccessible

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user-create')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->namingService->expects($this->once())
            ->method('generateClassName')
            ->with('user-create')
            ->willReturn('UserCreateDirective');

        $this->fileTask->expects($this->once())
            ->method('execute')
            ->with('UserCreateDirective', 'user-create')
            ->willReturn(new CreateDirectiveFileRecord(
                success: false,
                path: '/app/Directives/UserCreateDirective.php',
                error: 'Cannot create directory',
            ));

        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Cannot create directory');

        $result = $this->directive->execute();
        $this->assertSame(ExitCode::FAILURE, $result);
    }
}
