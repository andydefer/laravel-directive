<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Tasks;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class RenderTaskTest extends UnitTestCase
{
    private RenderDispatcher $task;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create a fresh RenderDispatcher instance for each test
        $this->task = new RenderDispatcher;
    }

    public function test_render_help(): void
    {
        // Arrange: Create help render record
        $record = new RenderRecord(type: RenderType::HELP);

        // Act: Execute render task
        $result = $this->task->execute($record, RenderType::HELP);

        // Assert: Verify help content is present
        $this->assertStringContainsString('Directive System', $result);
        $this->assertStringContainsString('USAGE:', $result);
    }

    public function test_render_list_with_directives(): void
    {
        // Arrange: Create directives collection
        $directives = new DirectiveMetadataCollection;

        $aliases = new StringTypedCollection;
        $directive = new DirectiveMetadataRecord(
            signature: 'test-cmd',
            class: \stdClass::class,
            description: 'Test command',
            aliases: $aliases,
        );
        $directives->add($directive);

        $record = new RenderRecord(type: RenderType::LIST, directives: $directives);

        // Act: Execute render task
        $result = $this->task->execute($record, RenderType::LIST);

        // Assert: Verify list content is present
        $this->assertStringContainsString('Available Directives', $result);
        $this->assertStringContainsString('test-cmd', $result);
        $this->assertStringContainsString('Test command', $result);
    }

    public function test_render_list_empty_falls_back_to_empty(): void
    {
        // Arrange: Create list record with no directives
        $record = new RenderRecord(type: RenderType::LIST, directives: null);

        // Act: Execute render task
        $result = $this->task->execute($record, RenderType::LIST);

        // Assert: Verify empty list message is displayed
        $this->assertStringContainsString('No Directives Found', $result);
    }

    public function test_render_not_found(): void
    {
        // Arrange: Create not found record
        $record = new RenderRecord(type: RenderType::NOT_FOUND, signature: 'unknown-cmd');

        // Act: Execute render task
        $result = $this->task->execute($record, RenderType::NOT_FOUND);

        // Assert: Verify not found message is displayed
        $this->assertStringContainsString('unknown-cmd', $result);
        $this->assertStringContainsString('not found', $result);
    }

    public function test_render_success(): void
    {
        // Arrange: Create success record
        $record = new RenderRecord(type: RenderType::SUCCESS, message: 'Operation OK');

        // Act: Execute render task
        $result = $this->task->execute($record, RenderType::SUCCESS);

        // Assert: Verify success message and color code are present
        $this->assertStringContainsString('Operation OK', $result);
        $this->assertStringContainsString("\033[32m", $result);
    }

    public function test_render_error(): void
    {
        // Arrange: Create error record
        $record = new RenderRecord(type: RenderType::ERROR, message: 'Something wrong');

        // Act: Execute render task
        $result = $this->task->execute($record, RenderType::ERROR);

        // Assert: Verify error message and color code are present
        $this->assertStringContainsString('Something wrong', $result);
        $this->assertStringContainsString("\033[31m", $result);
    }

    public function test_render_warning(): void
    {
        // Arrange: Create warning record
        $record = new RenderRecord(type: RenderType::WARNING, message: 'Be careful');

        // Act: Execute render task
        $result = $this->task->execute($record, RenderType::WARNING);

        // Assert: Verify warning message, color, and emoji are present
        $this->assertStringContainsString('Be careful', $result);
        $this->assertStringContainsString("\033[33m", $result);
        $this->assertStringContainsString('⚠️', $result);
    }

    public function test_render_debug(): void
    {
        // Arrange: Create debug record
        $record = new RenderRecord(type: RenderType::DEBUG, message: 'Debug info');

        // Act: Execute render task
        $result = $this->task->execute($record, RenderType::DEBUG);

        // Assert: Verify debug message, color, and label are present
        $this->assertStringContainsString('Debug info', $result);
        $this->assertStringContainsString("\033[36m", $result);
        $this->assertStringContainsString('[DEBUG]', $result);
    }

    public function test_render_version(): void
    {
        // Arrange: Create version record
        $record = new RenderRecord(type: RenderType::VERSION);

        // Act: Execute render task
        $result = $this->task->execute($record, RenderType::VERSION);

        // Assert: Verify version information is present
        $this->assertStringContainsString('Laravel Directive', $result);
        $this->assertStringContainsString('Version:', $result);
        $this->assertStringContainsString('PHP Version:', $result);
        $this->assertStringContainsString('Laravel Version:', $result);
    }

    public function test_render_conflict(): void
    {
        // Arrange: Create conflict record with multiple conflicting directives
        $classNames = new StringTypedCollection;
        $classNames->add('UserCreateDirective');

        $signatures = new StringTypedCollection;
        $signatures->add('user-create');

        $descriptions = new StringTypedCollection;
        $descriptions->add('Create a user');

        $record = new ConflictDisplayRecord(
            name: 'add-user',
            classNames: $classNames,
            signatures: $signatures,
            descriptions: $descriptions,
        );

        // Act: Execute render task
        $result = $this->task->execute($record, RenderType::CONFLICT);

        // Assert: Verify conflict information is displayed
        $this->assertStringContainsString('add-user', $result);
        $this->assertStringContainsString('UserCreateDirective', $result);
    }
}
