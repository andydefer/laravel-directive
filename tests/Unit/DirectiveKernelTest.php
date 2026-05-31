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

        // Arrange: Create mocked dependencies
        $this->executionService = $this->createMock(DirectiveExecutionService::class);
        $this->signatureValidator = $this->createMock(SignatureValidationService::class);
        $this->renderer = $this->createMock(DirectiveRendererService::class);

        // Arrange: Create kernel instance with mocked dependencies
        $this->kernel = new DirectiveKernel(
            $this->executionService,
            $this->signatureValidator,
            $this->renderer,
        );
    }

    // ==================== Tests sans arguments ====================

    public function test_run_without_arguments_shows_help(): void
    {
        // Arrange: Expect help execution
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '--help'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act: Run kernel without arguments
        $result = $this->kernel->run(['directive']);

        // Assert: Verify help was shown and exit code is success
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec options globales ====================

    public function test_run_with_help_option(): void
    {
        // Arrange: Expect help execution
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '--help'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act: Run kernel with --help option
        $result = $this->kernel->run(['directive', '--help']);

        // Assert: Verify help was shown and exit code is success
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_list_option(): void
    {
        // Arrange: Expect list execution
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '--list'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act: Run kernel with --list option
        $result = $this->kernel->run(['directive', '--list']);

        // Assert: Verify list was shown and exit code is success
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec signatures valides ====================

    public function test_run_with_valid_kebab_signature(): void
    {
        // Arrange: Expect signature validation to pass
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user-create')
            ->willReturn(new ValidationResultRecord(isValid: true));

        // Arrange: Expect execution service to be called
        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::SUCCESS);

        // Act: Run kernel with valid kebab-case signature
        $result = $this->kernel->run(['directive', 'user-create']);

        // Assert: Verify execution was successful
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_valid_single_word_signature(): void
    {
        // Arrange: Expect signature validation to pass
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('list')
            ->willReturn(new ValidationResultRecord(isValid: true));

        // Arrange: Expect execution service to be called
        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::SUCCESS);

        // Act: Run kernel with valid single-word signature
        $result = $this->kernel->run(['directive', 'list']);

        // Assert: Verify execution was successful
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_short_option_h(): void
    {
        // Arrange: Expect short option -h execution
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '-h'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act: Run kernel with -h option
        $result = $this->kernel->run(['directive', '-h']);

        // Assert: Verify help was shown and exit code is success
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_short_option_l(): void
    {
        // Arrange: Expect short option -l execution
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '-l'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act: Run kernel with -l option
        $result = $this->kernel->run(['directive', '-l']);

        // Assert: Verify list was shown and exit code is success
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec signatures invalides ====================

    public function test_run_with_invalid_at_signature_returns_error(): void
    {
        // Arrange: Create validation error record
        $validationRecord = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "create@user"'
        );

        // Arrange: Expect signature validation to fail
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('create@user')
            ->willReturn($validationRecord);

        // Arrange: Expect validation error to be rendered
        $this->renderer->expects($this->once())
            ->method('renderValidationError')
            ->with($validationRecord);

        // Arrange: Expect execution service not to be called
        $this->executionService->expects($this->never())
            ->method('execute');

        // Act: Run kernel with invalid @ signature
        $result = $this->kernel->run(['directive', 'create@user']);

        // Assert: Verify invalid argument exit code
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_run_with_underscore_signature_returns_error(): void
    {
        // Arrange: Create validation error record
        $validationRecord = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "create_user"'
        );

        // Arrange: Expect signature validation to fail
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('create_user')
            ->willReturn($validationRecord);

        // Arrange: Expect validation error to be rendered
        $this->renderer->expects($this->once())
            ->method('renderValidationError')
            ->with($validationRecord);

        // Arrange: Expect execution service not to be called
        $this->executionService->expects($this->never())
            ->method('execute');

        // Act: Run kernel with underscore signature
        $result = $this->kernel->run(['directive', 'create_user']);

        // Assert: Verify invalid argument exit code
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_run_with_trailing_hyphen_returns_error(): void
    {
        // Arrange: Create validation error record
        $validationRecord = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "user-"'
        );

        // Arrange: Expect signature validation to fail
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user-')
            ->willReturn($validationRecord);

        // Arrange: Expect validation error to be rendered
        $this->renderer->expects($this->once())
            ->method('renderValidationError')
            ->with($validationRecord);

        // Arrange: Expect execution service not to be called
        $this->executionService->expects($this->never())
            ->method('execute');

        // Act: Run kernel with trailing hyphen signature
        $result = $this->kernel->run(['directive', 'user-']);

        // Assert: Verify invalid argument exit code
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_run_with_leading_hyphen_returns_error(): void
    {
        // Arrange: Create validation error record
        $validationRecord = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "-list"'
        );

        // Arrange: Expect signature validation to fail
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('-list')
            ->willReturn($validationRecord);

        // Arrange: Expect validation error to be rendered
        $this->renderer->expects($this->once())
            ->method('renderValidationError')
            ->with($validationRecord);

        // Arrange: Expect execution service not to be called
        $this->executionService->expects($this->never())
            ->method('execute');

        // Act: Run kernel with leading hyphen signature
        $result = $this->kernel->run(['directive', '-list']);

        // Assert: Verify invalid argument exit code
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_run_with_consecutive_hyphens_returns_error(): void
    {
        // Arrange: Create validation error record
        $validationRecord = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "user--create"'
        );

        // Arrange: Expect signature validation to fail
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user--create')
            ->willReturn($validationRecord);

        // Arrange: Expect validation error to be rendered
        $this->renderer->expects($this->once())
            ->method('renderValidationError')
            ->with($validationRecord);

        // Arrange: Expect execution service not to be called
        $this->executionService->expects($this->never())
            ->method('execute');

        // Act: Run kernel with consecutive hyphens signature
        $result = $this->kernel->run(['directive', 'user--create']);

        // Assert: Verify invalid argument exit code
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    // ==================== Tests avec arguments ====================

    public function test_run_with_valid_signature_and_arguments(): void
    {
        // Arrange: Expect signature validation to pass
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('test-echo')
            ->willReturn(new ValidationResultRecord(isValid: true));

        // Arrange: Expect execution service with correct arguments
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === 'test-echo'
                    && $record->arguments->count() === 2
                    && $record->arguments->contains('Hello')
                    && $record->arguments->contains('World');
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act: Run kernel with signature and arguments
        $result = $this->kernel->run(['directive', 'test-echo', 'Hello', 'World']);

        // Assert: Verify execution was successful
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec codes de retour ====================

    public function test_run_returns_failure_when_directive_fails(): void
    {
        // Arrange: Expect signature validation to pass
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('test-echo')
            ->willReturn(new ValidationResultRecord(isValid: true));

        // Arrange: Expect execution service to return failure
        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::FAILURE);

        // Act: Run kernel with valid signature
        $result = $this->kernel->run(['directive', 'test-echo']);

        // Assert: Verify failure exit code
        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_run_returns_not_found_when_directive_does_not_exist(): void
    {
        // Arrange: Expect signature validation to pass
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('unknown-command')
            ->willReturn(new ValidationResultRecord(isValid: true));

        // Arrange: Expect execution service to return not found
        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::NOT_FOUND);

        // Act: Run kernel with unknown command
        $result = $this->kernel->run(['directive', 'unknown-command']);

        // Assert: Verify not found exit code
        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }
}
