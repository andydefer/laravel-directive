<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Tasks;

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Tasks\RenderTask;
use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class RenderTaskTest extends TestCase
{
    private RenderTask $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->task = new RenderTask();
    }

    public function test_render_help(): void
    {
        $record = new RenderRecord(type: RenderType::HELP);
        $result = $this->task->execute($record, RenderType::HELP);

        $this->assertStringContainsString('Directive System', $result);
        $this->assertStringContainsString('USAGE:', $result);
    }

    public function test_render_list_with_directives(): void
    {
        $directives = new TypedCollection(\stdClass::class);
        $directive = new \stdClass();
        $directive->signature = 'test-cmd';
        $directive->description = 'Test command';
        $directive->aliases = new StringTypedCollection();
        $directives->add($directive);

        $record = new RenderRecord(type: RenderType::LIST, directives: $directives);
        $result = $this->task->execute($record, RenderType::LIST);

        $this->assertStringContainsString('Available Directives', $result);
        $this->assertStringContainsString('test-cmd', $result);
        $this->assertStringContainsString('Test command', $result);
    }

    public function test_render_list_empty_falls_back_to_empty(): void
    {
        $record = new RenderRecord(type: RenderType::LIST, directives: null);
        $result = $this->task->execute($record, RenderType::LIST);

        $this->assertStringContainsString('No Directives Found', $result);
    }

    public function test_render_not_found(): void
    {
        $record = new RenderRecord(type: RenderType::NOT_FOUND, signature: 'unknown-cmd');
        $result = $this->task->execute($record, RenderType::NOT_FOUND);

        $this->assertStringContainsString('unknown-cmd', $result);
        $this->assertStringContainsString('not found', $result);
    }

    public function test_render_success(): void
    {
        $record = new RenderRecord(type: RenderType::SUCCESS, message: 'Operation OK');
        $result = $this->task->execute($record, RenderType::SUCCESS);

        $this->assertStringContainsString('Operation OK', $result);
        $this->assertStringContainsString("\033[32m", $result);
    }

    public function test_render_error(): void
    {
        $record = new RenderRecord(type: RenderType::ERROR, message: 'Something wrong');
        $result = $this->task->execute($record, RenderType::ERROR);

        $this->assertStringContainsString('Something wrong', $result);
        $this->assertStringContainsString("\033[31m", $result);
    }

    public function test_render_conflict(): void
    {
        $classNames = new StringTypedCollection();
        $classNames->add('UserCreateDirective');

        $signatures = new StringTypedCollection();
        $signatures->add('user-create');

        $descriptions = new StringTypedCollection();
        $descriptions->add('Create a user');

        $record = new ConflictDisplayRecord(
            name: 'add-user',
            classNames: $classNames,
            signatures: $signatures,
            descriptions: $descriptions,
        );

        $result = $this->task->execute($record, RenderType::CONFLICT);

        $this->assertStringContainsString('add-user', $result);
        $this->assertStringContainsString('UserCreateDirective', $result);
    }
}
