<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Tasks\RenderTask;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\MockObject\MockObject;

final class DirectiveRendererServiceTest extends UnitTestCase
{
    private RenderTask&MockObject $renderTask;

    private DirectiveRendererService $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderTask = $this->createMock(RenderTask::class);
        $this->renderer = new DirectiveRendererService($this->renderTask);
    }

    public function test_render_help_calls_render_task(): void
    {
        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('help output');

        ob_start();
        $this->renderer->renderHelp();
        $output = ob_get_clean();

        $this->assertEquals('help output', $output);
    }

    public function test_render_list_calls_render_task(): void
    {
        $directives = new TypedCollection(\stdClass::class);

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('list output');

        ob_start();
        $this->renderer->renderList($directives);
        $output = ob_get_clean();

        $this->assertEquals('list output', $output);
    }

    public function test_render_not_found_calls_render_task(): void
    {
        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('not found output');

        ob_start();
        $this->renderer->renderNotFound('test-cmd');
        $output = ob_get_clean();

        $this->assertEquals('not found output', $output);
    }

    public function test_render_success_calls_render_task(): void
    {
        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('success output');

        ob_start();
        $this->renderer->renderSuccess('Success message');
        $output = ob_get_clean();

        $this->assertEquals('success output', $output);
    }

    public function test_render_error_calls_render_task(): void
    {
        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('error output');

        ob_start();
        $this->renderer->renderError('Error message');
        $output = ob_get_clean();

        $this->assertEquals('error output', $output);
    }

    public function test_render_warning_calls_render_task(): void
    {
        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('warning output');

        ob_start();
        $this->renderer->renderWarning('Warning message');
        $output = ob_get_clean();

        $this->assertEquals('warning output', $output);
    }

    public function test_render_debug_does_nothing_when_debug_disabled(): void
    {
        // Ensure debug is disabled
        putenv('DIRECTIVE_DEBUG=false');

        $this->renderTask->expects($this->never())
            ->method('execute');

        ob_start();
        $this->renderer->renderDebug('Debug message');
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function test_render_debug_calls_render_task_when_debug_enabled(): void
    {
        putenv('DIRECTIVE_DEBUG=true');

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('debug output');

        ob_start();
        $this->renderer->renderDebug('Debug message');
        $output = ob_get_clean();

        $this->assertEquals('debug output', $output);

        putenv('DIRECTIVE_DEBUG=false');
    }

    public function test_render_version_calls_render_task(): void
    {
        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('version output');

        ob_start();
        $this->renderer->renderVersion();
        $output = ob_get_clean();

        $this->assertEquals('version output', $output);
    }

    public function test_render_conflict_calls_render_task(): void
    {
        $record = new ConflictDisplayRecord(
            name: 'test',
            classNames: new StringTypedCollection,
            signatures: new StringTypedCollection,
            descriptions: new StringTypedCollection,
        );

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('conflict output');

        ob_start();
        $this->renderer->renderConflict($record);
        $output = ob_get_clean();

        $this->assertEquals('conflict output', $output);
    }

    public function test_render_table_calls_render_task(): void
    {
        $rows = new RowCollection;

        $row = new RowCollection;
        $row->add('John Doe', 'john@example.com', 'Admin');
        $rows->add($row);

        $row2 = new RowCollection;
        $row2->add('Jane Smith', 'jane@example.com', 'User');
        $rows->add($row2);

        $record = new DisplayTableRecord(
            headers: new StringTypedCollection,
            rows: $rows,
        );

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('table output');

        ob_start();
        $this->renderer->renderTable($record);
        $output = ob_get_clean();

        $this->assertEquals('table output', $output);
    }

    public function test_render_validation_error_calls_render_task(): void
    {
        $record = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature',
        );

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->willReturn('validation error output');

        ob_start();
        $this->renderer->renderValidationError($record);
        $output = ob_get_clean();

        $this->assertEquals('validation error output', $output);
    }
}
