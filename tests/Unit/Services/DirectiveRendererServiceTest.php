<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \AndyDefer\Directive\Services\DirectiveRendererService
 */
final class DirectiveRendererServiceTest extends UnitTestCase
{
    private RenderDispatcher&MockObject $renderDispatcher;

    private DirectiveRendererService $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderDispatcher = $this->createMock(RenderDispatcher::class);
        $this->renderer = new DirectiveRendererService($this->renderDispatcher);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('DIRECTIVE_DEBUG');
        putenv('APP_DEBUG');
    }

    public function test_render_help_calls_render_task(): void
    {
        // Arrange: Set up render task expectation
        $expectedOutput = 'help output';
        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering help
        ob_start();
        $this->renderer->renderHelp();
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_list_calls_render_task(): void
    {
        // Arrange: Create empty directives collection
        $directives = new DirectiveMetadataCollection;
        $expectedOutput = 'list output';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering list
        ob_start();
        $this->renderer->renderList($directives);
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_not_found_calls_render_task(): void
    {
        // Arrange: Set up render task expectation
        $signature = 'test-cmd';
        $expectedOutput = 'not found output';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering not found
        ob_start();
        $this->renderer->renderNotFound($signature);
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_success_calls_render_task(): void
    {
        // Arrange: Set up render task expectation
        $message = 'Success message';
        $expectedOutput = 'success output';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering success
        ob_start();
        $this->renderer->renderSuccess($message);
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_error_calls_render_task(): void
    {
        // Arrange: Set up render task expectation
        $message = 'Error message';
        $expectedOutput = 'error output';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering error
        ob_start();
        $this->renderer->renderError($message);
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_warning_calls_render_task(): void
    {
        // Arrange: Set up render task expectation
        $message = 'Warning message';
        $expectedOutput = 'warning output';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering warning
        ob_start();
        $this->renderer->renderWarning($message);
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_debug_does_nothing_when_debug_disabled(): void
    {
        // Arrange: Disable debug mode
        putenv('DIRECTIVE_DEBUG=false');
        putenv('APP_DEBUG=false');

        $this->renderDispatcher->expects($this->never())
            ->method('execute');

        // Act: Capture output while rendering debug
        ob_start();
        $this->renderer->renderDebug('Debug message');
        $actualOutput = ob_get_clean();

        // Assert: Verify no output was generated
        $this->assertEmpty($actualOutput);
    }

    public function test_render_debug_calls_render_task_when_directive_debug_enabled(): void
    {
        // Arrange: Enable DIRECTIVE_DEBUG
        putenv('DIRECTIVE_DEBUG=true');
        putenv('APP_DEBUG=false');

        $expectedOutput = 'debug output';
        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering debug
        ob_start();
        $this->renderer->renderDebug('Debug message');
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_debug_calls_render_task_when_app_debug_enabled(): void
    {
        // Arrange: Enable APP_DEBUG
        putenv('DIRECTIVE_DEBUG=false');
        putenv('APP_DEBUG=true');

        $expectedOutput = 'debug output';
        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering debug
        ob_start();
        $this->renderer->renderDebug('Debug message');
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_version_calls_render_task(): void
    {
        // Arrange: Set up render task expectation
        $expectedOutput = 'version output';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering version
        ob_start();
        $this->renderer->renderVersion();
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_conflict_calls_render_task(): void
    {
        // Arrange: Create conflict display record
        $record = $this->createConflictDisplayRecord();
        $expectedOutput = 'conflict output';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering conflict
        ob_start();
        $this->renderer->renderConflict($record);
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_table_calls_render_task(): void
    {
        // Arrange: Create table display record
        $record = $this->createDisplayTableRecord();
        $expectedOutput = 'table output';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering table
        ob_start();
        $this->renderer->renderTable($record);
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_validation_error_calls_render_task(): void
    {
        // Arrange: Create validation error record
        $record = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature',
        );
        $expectedOutput = 'validation error output';

        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering validation error
        ob_start();
        $this->renderer->renderValidationError($record);
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_debug_with_both_debug_variables_enabled(): void
    {
        // Arrange: Enable both debug flags
        putenv('DIRECTIVE_DEBUG=true');
        putenv('APP_DEBUG=true');

        $expectedOutput = 'debug output';
        $this->renderDispatcher->expects($this->once())
            ->method('execute')
            ->willReturn($expectedOutput);

        // Act: Capture output while rendering debug
        ob_start();
        $this->renderer->renderDebug('Debug message');
        $actualOutput = ob_get_clean();

        // Assert: Verify output matches expected
        $this->assertSame($expectedOutput, $actualOutput);
    }

    public function test_render_debug_with_invalid_debug_values(): void
    {
        // Arrange: Set invalid debug values
        putenv('DIRECTIVE_DEBUG=1');
        putenv('APP_DEBUG=1');

        $this->renderDispatcher->expects($this->never())
            ->method('execute');

        // Act: Capture output while rendering debug
        ob_start();
        $this->renderer->renderDebug('Debug message');
        $actualOutput = ob_get_clean();

        // Assert: Verify no output was generated
        $this->assertEmpty($actualOutput);
    }

    /**
     * Create a conflict display record for testing.
     */
    private function createConflictDisplayRecord(): ConflictDisplayRecord
    {
        return new ConflictDisplayRecord(
            name: 'test',
            classNames: new StringTypedCollection,
            signatures: new StringTypedCollection,
            descriptions: new StringTypedCollection,
        );
    }

    /**
     * Create a display table record for testing.
     */
    private function createDisplayTableRecord(): DisplayTableRecord
    {
        $rows = $this->createTestRows();

        return new DisplayTableRecord(
            headers: new StringTypedCollection,
            rows: $rows,
        );
    }

    /**
     * Create test rows for table display.
     */
    private function createTestRows(): RowCollection
    {
        $firstRow = new RowCollection;
        $firstRow->add('John Doe', 'john@example.com', 'Admin');

        $secondRow = new RowCollection;
        $secondRow->add('Jane Smith', 'jane@example.com', 'User');

        $rows = new RowCollection;
        $rows->add($firstRow);
        $rows->add($secondRow);

        return $rows;
    }
}
