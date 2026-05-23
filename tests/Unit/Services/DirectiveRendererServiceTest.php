<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Tasks\RenderTask;
use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\MockObject\MockObject;

final class DirectiveRendererServiceTest extends TestCase
{
    private RenderTask&MockObject $renderTask;
    private DirectiveRendererService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderTask = $this->createMock(RenderTask::class);
        $this->service = new DirectiveRendererService($this->renderTask);
    }

    public function test_render_help(): void
    {
        $this->renderTask->expects($this->once())
            ->method('execute')
            ->with(
                $this->isInstanceOf(RenderRecord::class),
                RenderType::HELP
            )
            ->willReturn('HELP OUTPUT');

        $this->expectOutputString('HELP OUTPUT');
        $this->service->renderHelp();
    }

    public function test_render_list(): void
    {
        $directives = new TypedCollection(\stdClass::class);

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->with(
                $this->isInstanceOf(RenderRecord::class),
                RenderType::LIST
            )
            ->willReturn('LIST OUTPUT');

        $this->expectOutputString('LIST OUTPUT');
        $this->service->renderList($directives);
    }

    public function test_render_not_found(): void
    {
        $this->renderTask->expects($this->once())
            ->method('execute')
            ->with(
                $this->isInstanceOf(RenderRecord::class),
                RenderType::NOT_FOUND
            )
            ->willReturn('NOT FOUND OUTPUT');

        $this->expectOutputString('NOT FOUND OUTPUT');
        $this->service->renderNotFound('test-cmd');
    }

    public function test_render_success(): void
    {
        $this->renderTask->expects($this->once())
            ->method('execute')
            ->with(
                $this->isInstanceOf(RenderRecord::class),
                RenderType::SUCCESS
            )
            ->willReturn('SUCCESS OUTPUT');

        $this->expectOutputString('SUCCESS OUTPUT');
        $this->service->renderSuccess('OK');
    }

    public function test_render_error(): void
    {
        $this->renderTask->expects($this->once())
            ->method('execute')
            ->with(
                $this->isInstanceOf(RenderRecord::class),
                RenderType::ERROR
            )
            ->willReturn('ERROR OUTPUT');

        $this->expectOutputString('ERROR OUTPUT');
        $this->service->renderError('FAIL');
    }

    public function test_render_conflict(): void
    {
        $classNames = new StringTypedCollection();
        $classNames->add('TestDirective');

        $signatures = new StringTypedCollection();
        $signatures->add('test-cmd');

        $descriptions = new StringTypedCollection();
        $descriptions->add('Test description');

        $record = new ConflictDisplayRecord(
            name: 'test',
            classNames: $classNames,
            signatures: $signatures,
            descriptions: $descriptions,
        );

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->with($record, RenderType::CONFLICT)
            ->willReturn('CONFLICT OUTPUT');

        $this->expectOutputString('CONFLICT OUTPUT');
        $this->service->renderConflict($record);
    }

    public function test_render_table(): void
    {
        $headers = new StringTypedCollection();
        $headers->add('Name', 'Email');

        $rows = new RowCollection();
        $row = new RowCollection();
        $row->add('John', 'john@example.com');
        $rows->add($row);

        $record = new DisplayTableRecord($headers, $rows);

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->with($record, RenderType::TABLE)
            ->willReturn('TABLE OUTPUT');

        $this->expectOutputString('TABLE OUTPUT');
        $this->service->renderTable($record);
    }

    public function test_render_validation_error(): void
    {
        $record = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format'
        );

        $this->renderTask->expects($this->once())
            ->method('execute')
            ->with($record, RenderType::VALIDATION_ERROR)
            ->willReturn('VALIDATION ERROR OUTPUT');

        $this->expectOutputString('VALIDATION ERROR OUTPUT');
        $this->service->renderValidationError($record);
    }
}
