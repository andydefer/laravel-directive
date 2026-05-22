<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Unit\Services;

use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Tasks\DisplayErrorTask;
use AndyDefer\Directive\Tasks\DisplayMessageTask;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveExecutionServiceTest extends TestCase
{
    private DirectiveDiscoveryService&MockObject $discovery;
    private DirectiveParserService&MockObject $parser;
    private DirectiveHydratorService&MockObject $hydrator;
    private DirectiveRendererService&MockObject $renderer;
    private DisplayMessageTask&MockObject $displayMessage;
    private DisplayErrorTask&MockObject $displayError;
    private DirectiveExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discovery = $this->createMock(DirectiveDiscoveryService::class);
        $this->parser = $this->createMock(DirectiveParserService::class);
        $this->hydrator = $this->createMock(DirectiveHydratorService::class);
        $this->renderer = $this->createMock(DirectiveRendererService::class);
        $this->displayMessage = $this->createMock(DisplayMessageTask::class);
        $this->displayError = $this->createMock(DisplayErrorTask::class);

        $this->renderer->method('renderList')->willReturn('');
        $this->renderer->method('renderHelp')->willReturn('');
        $this->renderer->method('renderNotFound')->willReturn('Not found');

        $emptyDirectives = new TypedCollection(DirectiveMetadataRecord::class);
        $this->discovery->method('discover')->willReturn($emptyDirectives);

        $this->service = new DirectiveExecutionService(
            $this->discovery,
            $this->parser,
            $this->hydrator,
            $this->renderer,
            $this->displayMessage,
            $this->displayError,
        );
    }

    // ==================== Helper Methods ====================

    private function createTypedCollectionFromArray(array $items): StringTypedCollection
    {
        $collection = new StringTypedCollection();
        foreach ($items as $item) {
            $collection->add($item);
        }

        return $collection;
    }

    private function createDirectivesCollectionWithTestEcho(): TypedCollection
    {
        $aliases = new StringTypedCollection();
        $aliases->add('echo');

        $directiveMetadata = new DirectiveMetadataRecord(
            signature: 'test:echo',
            class: TestEchoDirective::class,
            description: 'Test echo directive',
            aliases: $aliases,
        );

        $directives = new TypedCollection(DirectiveMetadataRecord::class);
        $directives->add($directiveMetadata);

        return $directives;
    }

    private function createServiceWithDirectives(TypedCollection $directives): DirectiveExecutionService
    {
        $discovery = $this->createMock(DirectiveDiscoveryService::class);
        $discovery->method('discover')->willReturn($directives);

        return new DirectiveExecutionService(
            $discovery,
            $this->parser,
            $this->hydrator,
            $this->renderer,
            $this->displayMessage,
            $this->displayError,
        );
    }

    // ==================== Tests avec collection vide ====================

    public function test_execute_returns_success_for_list_command(): void
    {
        $arguments = new StringTypedCollection();
        $record = new DirectiveExecutionRecord(signature: '--list', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_success_for_help_command(): void
    {
        $arguments = new StringTypedCollection();
        $record = new DirectiveExecutionRecord(signature: '--help', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_not_found_when_directive_does_not_exist(): void
    {
        $arguments = new StringTypedCollection();
        $record = new DirectiveExecutionRecord(signature: 'unknown:command', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }

    public function test_exists_returns_false_for_non_existing_directive(): void
    {
        $result = $this->service->exists('unknown:command');

        $this->assertFalse($result);
    }

    public function test_list_directives_returns_empty_when_no_directives(): void
    {
        $result = $this->service->listDirectives();

        $this->assertSame(0, $result->count());
    }

    // ==================== Tests avec directives existantes ====================

    public function test_execute_returns_success_when_directive_exists(): void
    {
        $directives = $this->createDirectivesCollectionWithTestEcho();
        $service = $this->createServiceWithDirectives($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection(),
            options: new StringTypedCollection(),
        );
        $this->parser->method('parse')->willReturn($parsedRecord);

        $command = $this->createMock(DirectiveInterface::class);
        $command->method('execute')->willReturn(ExitCode::SUCCESS);
        $this->hydrator->method('hydrate')->willReturn($command);

        $arguments = $this->createTypedCollectionFromArray(['Hello']);
        $record = new DirectiveExecutionRecord(signature: 'test:echo', arguments: $arguments);

        $result = $service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_exists_returns_true_for_existing_directive(): void
    {
        $directives = $this->createDirectivesCollectionWithTestEcho();
        $service = $this->createServiceWithDirectives($directives);

        $result = $service->exists('test:echo');

        $this->assertTrue($result);
    }

    public function test_list_directives_returns_all_directives(): void
    {
        $aliases1 = new StringTypedCollection();
        $directive1 = new DirectiveMetadataRecord(
            signature: 'test:echo',
            class: TestEchoDirective::class,
            description: 'Test echo directive',
            aliases: $aliases1,
        );

        $aliases2 = new StringTypedCollection();
        $directive2 = new DirectiveMetadataRecord(
            signature: 'cache:clear',
            class: 'CacheDirective',
            description: 'Clear cache',
            aliases: $aliases2,
        );

        $directives = new TypedCollection(DirectiveMetadataRecord::class);
        $directives->add($directive1, $directive2);

        $service = $this->createServiceWithDirectives($directives);

        $result = $service->listDirectives();

        $this->assertSame(2, $result->count());
    }

    public function test_find_directive_by_signature_returns_directive_when_exists(): void
    {
        $directives = $this->createDirectivesCollectionWithTestEcho();
        $service = $this->createServiceWithDirectives($directives);

        $result = $service->findDirectiveBySignature('test:echo');

        $this->assertNotNull($result);
        $this->assertSame('test:echo', $result->signature);
    }

    public function test_find_directive_by_signature_returns_null_when_not_exists(): void
    {
        $result = $this->service->findDirectiveBySignature('unknown:command');

        $this->assertNull($result);
    }

    public function test_find_directive_by_signature_works_with_alias(): void
    {
        $directives = $this->createDirectivesCollectionWithTestEcho();
        $service = $this->createServiceWithDirectives($directives);

        $result = $service->findDirectiveBySignature('echo');

        $this->assertNotNull($result);
        $this->assertSame('test:echo', $result->signature);
    }

    // ==================== Tests supplémentaires ====================

    public function test_execute_handles_directive_with_arguments(): void
    {
        $directives = $this->createDirectivesCollectionWithTestEcho();
        $service = $this->createServiceWithDirectives($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection(),
            options: new StringTypedCollection(),
        );
        $this->parser->method('parse')->willReturn($parsedRecord);

        $command = $this->createMock(DirectiveInterface::class);
        $command->method('execute')->willReturn(ExitCode::SUCCESS);
        $this->hydrator->method('hydrate')->willReturn($command);

        $arguments = $this->createTypedCollectionFromArray(['John', '--role=admin']);
        $record = new DirectiveExecutionRecord(signature: 'test:echo', arguments: $arguments);

        $result = $service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_directive_with_options(): void
    {
        $directives = $this->createDirectivesCollectionWithTestEcho();
        $service = $this->createServiceWithDirectives($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection(),
            options: new StringTypedCollection(),
        );
        $this->parser->method('parse')->willReturn($parsedRecord);

        $command = $this->createMock(DirectiveInterface::class);
        $command->method('execute')->willReturn(ExitCode::SUCCESS);
        $this->hydrator->method('hydrate')->willReturn($command);

        $arguments = $this->createTypedCollectionFromArray(['--verbose', '--force']);
        $record = new DirectiveExecutionRecord(signature: 'test:echo', arguments: $arguments);

        $result = $service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_find_directive_by_signature_returns_null_for_empty_alias(): void
    {
        $aliases = new StringTypedCollection();
        $directiveMetadata = new DirectiveMetadataRecord(
            signature: 'test:echo',
            class: TestEchoDirective::class,
            description: 'Test echo directive',
            aliases: $aliases,
        );

        $directives = new TypedCollection(DirectiveMetadataRecord::class);
        $directives->add($directiveMetadata);

        $service = $this->createServiceWithDirectives($directives);

        $result = $service->findDirectiveBySignature('echo');

        $this->assertNull($result);
    }
}
