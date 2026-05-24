<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

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
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestLaravelDirective;
use AndyDefer\Directive\Tests\Fixtures\RegisteredDirectives\TestPackageDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveExecutionServiceTest extends UnitTestCase
{
    private DirectiveDiscoveryService&MockObject $discovery;

    private DirectiveParserService&MockObject $parser;

    private DirectiveHydratorService&MockObject $hydrator;

    private DirectiveRendererService&MockObject $renderer;

    private DirectiveExecutionService $service;

    // Changement: protected au lieu de private pour correspondre à TestCase
    protected LaravelBootstrapper $laravelBootstrapper;

    private string|false $originalDebug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discovery = $this->createMock(DirectiveDiscoveryService::class);
        $this->parser = $this->createMock(DirectiveParserService::class);
        $this->hydrator = $this->createMock(DirectiveHydratorService::class);
        $this->renderer = $this->createMock(DirectiveRendererService::class);
        $this->laravelBootstrapper = new LaravelBootstrapper;

        $this->service = new DirectiveExecutionService(
            discovery: $this->discovery,
            parser: $this->parser,
            hydrator: $this->hydrator,
            renderer: $this->renderer,
        );
        $this->service->setLaravelBootstrapper($this->laravelBootstrapper);

        // Sauvegarder l'état original de DIRECTIVE_DEBUG
        $this->originalDebug = getenv('DIRECTIVE_DEBUG');
    }

    protected function tearDown(): void
    {
        // Restaurer l'état original de DIRECTIVE_DEBUG
        if ($this->originalDebug === false) {
            putenv('DIRECTIVE_DEBUG');
        } else {
            putenv('DIRECTIVE_DEBUG='.$this->originalDebug);
        }

        $this->laravelBootstrapper->reset();
        parent::tearDown();
    }

    private function createArguments(array $items): StringTypedCollection
    {
        $collection = new StringTypedCollection;
        foreach ($items as $item) {
            $collection->add($item);
        }

        return $collection;
    }

    private function createDirectivesCollection(): TypedCollection
    {
        $collection = new TypedCollection(DirectiveMetadataRecord::class);

        $aliases1 = new StringTypedCollection;
        $directive1 = new DirectiveMetadataRecord(
            signature: 'test-concrete',
            class: TestConcreteDirective::class,
            description: 'Test concrete directive',
            aliases: $aliases1,
        );
        $collection->add($directive1);

        $aliases2 = new StringTypedCollection;
        $aliases2->add('tpkg');
        $directive2 = new DirectiveMetadataRecord(
            signature: 'test-package',
            class: TestPackageDirective::class,
            description: 'Test package directive',
            aliases: $aliases2,
        );
        $collection->add($directive2);

        $aliases3 = new StringTypedCollection;
        $directive3 = new DirectiveMetadataRecord(
            signature: 'test-laravel',
            class: TestLaravelDirective::class,
            description: 'Test Laravel directive',
            aliases: $aliases3,
        );
        $collection->add($directive3);

        return $collection;
    }

    // ==================== Tests avec signature non trouvée ====================

    public function test_execute_returns_not_found_when_directive_does_not_exist(): void
    {
        $directives = new TypedCollection(DirectiveMetadataRecord::class);

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

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
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection,
            options: new StringTypedCollection,
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
        $record = new DirectiveExecutionRecord(signature: 'test-concrete', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_failure_when_directive_fails(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection,
            options: new StringTypedCollection,
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
        $record = new DirectiveExecutionRecord(signature: 'test-concrete', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_execute_handles_directive_by_alias(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection,
            options: new StringTypedCollection,
        );
        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-package', $this->createArguments([]))
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

    // ==================== Tests des commandes intégrées ====================

    public function test_execute_handles_help_command(): void
    {
        $this->renderer->expects($this->once())
            ->method('renderHelp');

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: '--help', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_short_help_command(): void
    {
        $this->renderer->expects($this->once())
            ->method('renderHelp');

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: '-h', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_list_command(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $this->renderer->expects($this->once())
            ->method('renderList')
            ->with($directives);

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: '--list', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_short_list_command(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $this->renderer->expects($this->once())
            ->method('renderList')
            ->with($directives);

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: '-l', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_version_command(): void
    {
        $this->renderer->expects($this->once())
            ->method('renderVersion');

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: '--version', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_short_version_command(): void
    {
        $this->renderer->expects($this->once())
            ->method('renderVersion');

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: '-v', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Tests avec arguments et options ====================

    public function test_execute_passes_arguments_and_options_to_parser(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $arguments = $this->createArguments(['John', '--role=admin', '--verbose']);

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $arguments)
            ->willReturn(new ParsedDirectiveRecord(
                arguments: new StringTypedCollection,
                options: new StringTypedCollection,
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

        $record = new DirectiveExecutionRecord(signature: 'test-concrete', arguments: $arguments);

        $this->service->execute($record);
    }

    // ==================== Tests avec Laravel Bootstrap ====================

    public function test_execute_boots_laravel_when_directive_requests_it(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new StringTypedCollection,
            options: new StringTypedCollection,
        );
        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-laravel', $this->createArguments([]))
            ->willReturn($parsedRecord);

        $directive = $this->createMock(DirectiveInterface::class);
        $directive->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::SUCCESS);

        $this->hydrator->expects($this->once())
            ->method('hydrate')
            ->with(TestLaravelDirective::class, $parsedRecord)
            ->willReturn($directive);

        $this->renderer->expects($this->once())
            ->method('renderSuccess');

        $this->renderer->expects($this->once())
            ->method('renderWarning')
            ->with($this->stringContains('Laravel bootstrap file not found'));

        $arguments = $this->createArguments([]);
        $record = new DirectiveExecutionRecord(signature: 'test-laravel', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }
}
