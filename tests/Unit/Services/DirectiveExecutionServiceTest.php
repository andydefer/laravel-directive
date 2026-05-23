<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRegistrar;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\Fixtures\RegisteredDirectives\TestPackageDirective;
use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveExecutionServiceTest extends TestCase
{
    private DirectiveParserService&MockObject $parser;
    private DirectiveHydratorService&MockObject $hydrator;
    private DirectiveRendererService&MockObject $renderer;
    private DirectiveRegistrar&MockObject $registrar;
    private DirectiveInteractionService&MockObject $interaction;
    private DirectiveExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = $this->createMock(DirectiveParserService::class);
        $this->hydrator = $this->createMock(DirectiveHydratorService::class);
        $this->renderer = $this->createMock(DirectiveRendererService::class);
        $this->registrar = $this->createMock(DirectiveRegistrar::class);
        $this->interaction = $this->createMock(DirectiveInteractionService::class);

        $this->service = new DirectiveExecutionService(
            parser: $this->parser,
            hydrator: $this->hydrator,
            renderer: $this->renderer,
            registrar: $this->registrar,
            interaction: $this->interaction,
        );
    }

    private function createArguments(array $items): StringTypedCollection
    {
        $collection = new StringTypedCollection();
        foreach ($items as $item) {
            $collection->add($item);
        }
        return $collection;
    }

    private function createClassCollection(array $classes): StringTypedCollection
    {
        $collection = new StringTypedCollection();
        foreach ($classes as $class) {
            $collection->add($class);
        }
        return $collection;
    }

    // ==================== Tests avec signature non trouvée ====================

    public function test_execute_returns_not_found_when_directive_does_not_exist(): void
    {
        $this->registrar->expects($this->once())
            ->method('find')
            ->with('unknown-cmd')
            ->willReturn(new StringTypedCollection());

        $this->renderer->expects($this->once())
            ->method('renderNotFound')
            ->with('unknown-cmd');

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: 'unknown-cmd', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }

    // ==================== Tests avec une seule directive ====================

    public function test_execute_returns_success_when_directive_exists(): void
    {
        $classes = $this->createClassCollection([TestConcreteDirective::class]);

        $this->registrar->expects($this->once())
            ->method('find')
            ->with('test-cmd')
            ->willReturn($classes);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection(),
            options: new StringTypedCollection(),
        );
        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $this->createArguments(['John']))
            ->willReturn($parsedRecord);

        $directive = $this->createMock(DirectiveInterface::class);
        $directive->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::SUCCESS);

        $this->hydrator->expects($this->once())
            ->method('hydrate')
            ->with(TestConcreteDirective::class, $parsedRecord)
            ->willReturn($directive);

        $this->renderer->expects($this->once())
            ->method('renderSuccess')
            ->with('Directive executed successfully');

        $arguments = $this->createArguments(['John']);
        $record = new DirectiveExecutionRecord(signature: 'test-cmd', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_failure_when_directive_fails(): void
    {
        $classes = $this->createClassCollection([TestConcreteDirective::class]);

        $this->registrar->expects($this->once())
            ->method('find')
            ->with('test-cmd')
            ->willReturn($classes);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection(),
            options: new StringTypedCollection(),
        );
        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $this->createArguments([]))
            ->willReturn($parsedRecord);

        $directive = $this->createMock(DirectiveInterface::class);
        $directive->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::FAILURE);

        $this->hydrator->expects($this->once())
            ->method('hydrate')
            ->with(TestConcreteDirective::class, $parsedRecord)
            ->willReturn($directive);

        $this->renderer->expects($this->once())
            ->method('renderError')
            ->with('Directive execution failed');

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: 'test-cmd', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    // ==================== Tests avec conflit d'alias ====================

    public function test_execute_handles_conflict_and_user_chooses_first_option(): void
    {
        $classes = $this->createClassCollection([TestPackageDirective::class, TestConcreteDirective::class]);

        $this->registrar->expects($this->exactly(2))
            ->method('find')
            ->with('tpkg')
            ->willReturn($classes);

        $this->renderer->expects($this->once())
            ->method('renderConflict')
            ->with($this->callback(function (ConflictDisplayRecord $record) {
                return $record->name === 'tpkg'
                    && $record->classNames->count() === 2;
            }));

        $this->interaction->expects($this->once())
            ->method('askUserChoice')
            ->with('tpkg', 2)
            ->willReturn(1);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection(),
            options: new StringTypedCollection(),
        );
        $this->parser->expects($this->once())
            ->method('parse')
            ->willReturn($parsedRecord);

        $directive = $this->createMock(DirectiveInterface::class);
        $directive->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::SUCCESS);

        $this->hydrator->expects($this->once())
            ->method('hydrate')
            ->with(TestPackageDirective::class, $parsedRecord)
            ->willReturn($directive);

        $this->renderer->expects($this->once())
            ->method('renderSuccess')
            ->with('Directive executed successfully');

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: 'tpkg', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_conflict_and_user_chooses_second_option(): void
    {
        $classes = $this->createClassCollection([TestPackageDirective::class, TestConcreteDirective::class]);

        $this->registrar->expects($this->exactly(2))
            ->method('find')
            ->with('tpkg')
            ->willReturn($classes);

        $this->renderer->expects($this->once())
            ->method('renderConflict');

        $this->interaction->expects($this->once())
            ->method('askUserChoice')
            ->with('tpkg', 2)
            ->willReturn(2);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection(),
            options: new StringTypedCollection(),
        );
        $this->parser->expects($this->once())
            ->method('parse')
            ->willReturn($parsedRecord);

        $directive = $this->createMock(DirectiveInterface::class);
        $directive->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::SUCCESS);

        $this->hydrator->expects($this->once())
            ->method('hydrate')
            ->with(TestConcreteDirective::class, $parsedRecord)
            ->willReturn($directive);

        $this->renderer->expects($this->once())
            ->method('renderSuccess')
            ->with('Directive executed successfully');

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: 'tpkg', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_conflict_and_user_choice_invalid(): void
    {
        $classes = $this->createClassCollection([TestPackageDirective::class, TestConcreteDirective::class]);

        $this->registrar->expects($this->exactly(2))
            ->method('find')
            ->with('tpkg')
            ->willReturn($classes);

        $this->renderer->expects($this->once())
            ->method('renderConflict');

        $this->interaction->expects($this->once())
            ->method('askUserChoice')
            ->with('tpkg', 2)
            ->willReturn(0);

        $this->renderer->expects($this->once())
            ->method('renderError')
            ->with('Invalid choice');

        $this->hydrator->expects($this->never())
            ->method('hydrate');

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: 'tpkg', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    // ==================== Tests avec arguments et options ====================

    public function test_execute_passes_arguments_and_options_to_parser(): void
    {
        $classes = $this->createClassCollection([TestConcreteDirective::class]);

        $this->registrar->expects($this->once())
            ->method('find')
            ->with('test-cmd')
            ->willReturn($classes);

        $arguments = $this->createArguments(['John', '--role=admin', '--verbose']);

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $arguments)
            ->willReturn(new ParsedDirectiveRecord(
                arguments: new StringTypedCollection(),
                options: new StringTypedCollection(),
            ));

        $directive = $this->createMock(DirectiveInterface::class);
        $directive->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::SUCCESS);

        $this->hydrator->expects($this->once())
            ->method('hydrate')
            ->with(TestConcreteDirective::class, $this->isInstanceOf(ParsedDirectiveRecord::class))
            ->willReturn($directive);

        $this->renderer->expects($this->once())
            ->method('renderSuccess');

        $record = new DirectiveExecutionRecord(signature: 'test-cmd', arguments: $arguments);

        $this->service->execute($record);
    }
}
