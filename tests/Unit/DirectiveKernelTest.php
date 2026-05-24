<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveKernelTest extends UnitTestCase
{
    private DirectiveExecutionService&MockObject $executionService;

    private SignatureValidationService&MockObject $signatureValidator;

    private DirectiveRendererService&MockObject $renderer;

    private DirectiveKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executionService = $this->createMock(DirectiveExecutionService::class);
        $this->signatureValidator = $this->createMock(SignatureValidationService::class);
        $this->renderer = $this->createMock(DirectiveRendererService::class);

        $this->kernel = new DirectiveKernel(
            $this->executionService,
            $this->signatureValidator,
            $this->renderer,
        );
    }

    // ==================== Tests sans arguments ====================

    public function test_run_without_arguments_shows_help(): void
    {
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '--help'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        $result = $this->kernel->run(['directive']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec options globales ====================

    public function test_run_with_help_option(): void
    {
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '--help'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        $result = $this->kernel->run(['directive', '--help']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_list_option(): void
    {
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '--list'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        $result = $this->kernel->run(['directive', '--list']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec signatures valides ====================

    public function test_run_with_valid_kebab_signature(): void
    {
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user-create')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::SUCCESS);

        $result = $this->kernel->run(['directive', 'user-create']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_valid_single_word_signature(): void
    {
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('list')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::SUCCESS);

        $result = $this->kernel->run(['directive', 'list']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_short_option_h(): void
    {
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '-h'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        $result = $this->kernel->run(['directive', '-h']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_short_option_l(): void
    {
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '-l'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        $result = $this->kernel->run(['directive', '-l']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec signatures invalides ====================

    public function test_run_with_invalid_at_signature_returns_error(): void
    {
        $validationRecord = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "create@user"'
        );

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('create@user')
            ->willReturn($validationRecord);

        $this->renderer->expects($this->once())
            ->method('renderValidationError')
            ->with($validationRecord);

        $this->executionService->expects($this->never())
            ->method('execute');

        $result = $this->kernel->run(['directive', 'create@user']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_run_with_underscore_signature_returns_error(): void
    {
        $validationRecord = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "create_user"'
        );

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('create_user')
            ->willReturn($validationRecord);

        $this->renderer->expects($this->once())
            ->method('renderValidationError')
            ->with($validationRecord);

        $this->executionService->expects($this->never())
            ->method('execute');

        $result = $this->kernel->run(['directive', 'create_user']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_run_with_trailing_hyphen_returns_error(): void
    {
        $validationRecord = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "user-"'
        );

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user-')
            ->willReturn($validationRecord);

        $this->renderer->expects($this->once())
            ->method('renderValidationError')
            ->with($validationRecord);

        $this->executionService->expects($this->never())
            ->method('execute');

        $result = $this->kernel->run(['directive', 'user-']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_run_with_leading_hyphen_returns_error(): void
    {
        $validationRecord = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "-list"'
        );

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('-list')
            ->willReturn($validationRecord);

        $this->renderer->expects($this->once())
            ->method('renderValidationError')
            ->with($validationRecord);

        $this->executionService->expects($this->never())
            ->method('execute');

        $result = $this->kernel->run(['directive', '-list']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_run_with_consecutive_hyphens_returns_error(): void
    {
        $validationRecord = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "user--create"'
        );

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user--create')
            ->willReturn($validationRecord);

        $this->renderer->expects($this->once())
            ->method('renderValidationError')
            ->with($validationRecord);

        $this->executionService->expects($this->never())
            ->method('execute');

        $result = $this->kernel->run(['directive', 'user--create']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    // ==================== Tests avec arguments ====================

    public function test_run_with_valid_signature_and_arguments(): void
    {
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('test-echo')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === 'test-echo'
                    && $record->arguments->count() === 2
                    && $record->arguments->contains('Hello')
                    && $record->arguments->contains('World');
            }))
            ->willReturn(ExitCode::SUCCESS);

        $result = $this->kernel->run(['directive', 'test-echo', 'Hello', 'World']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec codes de retour ====================

    public function test_run_returns_failure_when_directive_fails(): void
    {
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('test-echo')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::FAILURE);

        $result = $this->kernel->run(['directive', 'test-echo']);

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_run_returns_not_found_when_directive_does_not_exist(): void
    {
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('unknown-command')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::NOT_FOUND);

        $result = $this->kernel->run(['directive', 'unknown-command']);

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }
}
