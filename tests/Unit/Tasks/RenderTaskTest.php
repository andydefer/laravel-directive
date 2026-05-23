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
    private string $tempDir;
    private RenderTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/stubs_test_' . uniqid();
        mkdir($this->tempDir);

        $this->createTestStubs();

        $this->task = new RenderTask($this->tempDir . '/');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        array_map('unlink', glob($this->tempDir . '/*.stub'));
        rmdir($this->tempDir);
    }

    private function createTestStubs(): void
    {
        file_put_contents($this->tempDir . '/help.stub', 'HELP CONTENT');
        file_put_contents($this->tempDir . '/list.stub', "COUNT: {{count}}\nROWS:\n{{rows}}");
        file_put_contents($this->tempDir . '/not-found.stub', 'NOT FOUND: {{signature}}');
        file_put_contents($this->tempDir . '/success.stub', 'SUCCESS: {{message}}');
        file_put_contents($this->tempDir . '/error.stub', 'ERROR: {{message}}');
        file_put_contents($this->tempDir . '/empty.stub', 'EMPTY DIRECTIVES');
        file_put_contents($this->tempDir . '/conflict.stub', "CONFLICT: {{name}}\n{{options}}");
        file_put_contents($this->tempDir . '/table.stub', "{{table}}");
    }

    public function test_render_help(): void
    {
        $record = new RenderRecord(type: RenderType::HELP);
        $result = $this->task->execute($record, RenderType::HELP);

        $this->assertSame('HELP CONTENT', $result);
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

        $this->assertStringContainsString('COUNT: 1', $result);
        $this->assertStringContainsString('test-cmd', $result);
    }

    public function test_render_list_empty_falls_back_to_empty(): void
    {
        $record = new RenderRecord(type: RenderType::LIST, directives: null);
        $result = $this->task->execute($record, RenderType::LIST);

        $this->assertSame('EMPTY DIRECTIVES', $result);
    }

    public function test_render_not_found(): void
    {
        $record = new RenderRecord(type: RenderType::NOT_FOUND, signature: 'unknown-cmd');
        $result = $this->task->execute($record, RenderType::NOT_FOUND);

        $this->assertSame('NOT FOUND: unknown-cmd', $result);
    }

    public function test_render_success(): void
    {
        $record = new RenderRecord(type: RenderType::SUCCESS, message: 'Operation OK');
        $result = $this->task->execute($record, RenderType::SUCCESS);

        $this->assertSame('SUCCESS: Operation OK', $result);
    }

    public function test_render_error(): void
    {
        $record = new RenderRecord(type: RenderType::ERROR, message: 'Something wrong');
        $result = $this->task->execute($record, RenderType::ERROR);

        $this->assertSame('ERROR: Something wrong', $result);
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

        $this->assertStringContainsString('CONFLICT: add-user', $result);
        $this->assertStringContainsString('UserCreateDirective', $result);
    }
}
