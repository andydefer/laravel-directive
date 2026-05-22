<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveKernelTest extends TestCase
{
    private DirectiveExecutionService&MockObject $executionService;

    private DirectiveKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executionService = $this->createMock(DirectiveExecutionService::class);
        $this->kernel = new DirectiveKernel($this->executionService);
    }

    // ==================== Tests sans arguments ====================

    public function test_run_without_arguments_shows_help(): void
    {
        // Arrange
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '--help'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act
        $result = $this->kernel->run(['directive']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec options globales ====================

    public function test_run_with_help_option(): void
    {
        // Arrange
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '--help'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act
        $result = $this->kernel->run(['directive', '--help']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_list_option(): void
    {
        // Arrange
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === '--list'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act
        $result = $this->kernel->run(['directive', '--list']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec directives ====================

    public function test_run_with_valid_directive_and_no_arguments(): void
    {
        // Arrange
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === 'test:echo'
                    && $record->arguments->count() === 0;
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act
        $result = $this->kernel->run(['directive', 'test:echo']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_valid_directive_and_arguments(): void
    {
        // Arrange
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === 'test:echo'
                    && $record->arguments->count() === 2
                    && $record->arguments->contains('Hello')
                    && $record->arguments->contains('World');
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act
        $result = $this->kernel->run(['directive', 'test:echo', 'Hello', 'World']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_valid_directive_and_options(): void
    {
        // Arrange
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === 'test:echo'
                    && $record->arguments->count() === 2
                    && $record->arguments->contains('--verbose')
                    && $record->arguments->contains('--force');
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act
        $result = $this->kernel->run(['directive', 'test:echo', '--verbose', '--force']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_run_with_valid_directive_and_mixed_arguments(): void
    {
        // Arrange
        $this->executionService->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DirectiveExecutionRecord $record): bool {
                return $record->signature === 'test:echo'
                    && $record->arguments->count() === 3
                    && $record->arguments->contains('John')
                    && $record->arguments->contains('--role=admin')
                    && $record->arguments->contains('--active');
            }))
            ->willReturn(ExitCode::SUCCESS);

        // Act
        $result = $this->kernel->run(['directive', 'test:echo', 'John', '--role=admin', '--active']);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec codes de retour ====================

    public function test_run_returns_failure_when_directive_fails(): void
    {
        // Arrange
        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::FAILURE);

        // Act
        $result = $this->kernel->run(['directive', 'test:echo']);

        // Assert
        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_run_returns_not_found_when_directive_does_not_exist(): void
    {
        // Arrange
        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::NOT_FOUND);

        // Act
        $result = $this->kernel->run(['directive', 'unknown:command']);

        // Assert
        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }

    public function test_run_returns_invalid_argument_when_arguments_are_invalid(): void
    {
        // Arrange
        $this->executionService->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::INVALID_ARGUMENT);

        // Act
        $result = $this->kernel->run(['directive', 'test:echo']);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }
}
