<?php

// tests/Unit/Services/DirectiveExecutionServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
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
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestLaravelDirective;
use AndyDefer\Directive\Tests\Fixtures\RegisteredDirectives\TestPackageDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Services\HydrationService;
use InvalidArgumentException;
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

    protected LaravelBootstrapperContext $laravelBootstrapperContext;

    private HydrationService $hydration;

    private string|false $originalDebug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hydration = new HydrationService;

        $this->discovery = $this->createMock(DirectiveDiscoveryService::class);
        $this->parser = $this->createMock(DirectiveParserService::class);
        $this->hydrator = $this->createMock(DirectiveHydratorService::class);
        $this->renderer = $this->createMock(DirectiveRendererService::class);
        $this->laravelBootstrapperContext = new LaravelBootstrapperContext;

        $this->service = new DirectiveExecutionService(
            discovery: $this->discovery,
            parser: $this->parser,
            hydrator: $this->hydrator,
            renderer: $this->renderer,
        );
        $this->service->setLaravelBootstrapper($this->laravelBootstrapperContext);

        $this->originalDebug = getenv('DIRECTIVE_DEBUG');
    }

    protected function tearDown(): void
    {
        if ($this->originalDebug === false) {
            putenv('DIRECTIVE_DEBUG');
        } else {
            putenv('DIRECTIVE_DEBUG=' . $this->originalDebug);
        }

        $this->laravelBootstrapperContext->reset();
        parent::tearDown();
    }

    private function createDirectivesCollection(): DirectiveMetadataCollection
    {
        $collection = new DirectiveMetadataCollection;

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

    private function createEmptyParsedDirectiveRecord(): ParsedDirectiveRecord
    {
        return new ParsedDirectiveRecord(
            arguments: new ParsedArgumentCollection,
            options: new ParsedOptionCollection,
            variadic_arguments: new StringTypedCollection,
        );
    }

    private function createExecutionRecord(string $signature, array $arguments = []): DirectiveExecutionRecord
    {
        return $this->hydration->hydrate(DirectiveExecutionRecord::class, [
            'signature' => $signature,
            'arguments' => $arguments,
        ]);
    }

    // ==================== Not Found Tests ====================

    public function test_execute_returns_not_found_when_directive_does_not_exist(): void
    {
        $directives = new DirectiveMetadataCollection;

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $this->renderer->expects($this->once())
            ->method('renderNotFound')
            ->with('unknown-cmd');

        $record = $this->createExecutionRecord('unknown-cmd', []);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }

    // ==================== Success/Failure Tests ====================

    public function test_execute_returns_success_when_directive_exists(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = $this->createEmptyParsedDirectiveRecord();

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $this->isInstanceOf(StringTypedCollection::class))
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

        $record = $this->createExecutionRecord('test-concrete', ['John']);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_failure_when_directive_fails(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = $this->createEmptyParsedDirectiveRecord();

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $this->isInstanceOf(StringTypedCollection::class))
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

        $record = $this->createExecutionRecord('test-concrete', []);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_execute_handles_directive_by_alias(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = $this->createEmptyParsedDirectiveRecord();

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-package', $this->isInstanceOf(StringTypedCollection::class))
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

        $record = $this->createExecutionRecord('tpkg', []);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Built-in Commands Tests ====================

    public function test_execute_handles_help_command(): void
    {
        $this->renderer->expects($this->once())
            ->method('renderHelp');

        $record = $this->createExecutionRecord('--help', []);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_short_help_command(): void
    {
        $this->renderer->expects($this->once())
            ->method('renderHelp');

        $record = $this->createExecutionRecord('-h', []);

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

        $record = $this->createExecutionRecord('--list', []);

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

        $record = $this->createExecutionRecord('-l', []);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_version_command(): void
    {
        $this->renderer->expects($this->once())
            ->method('renderVersion');

        $record = $this->createExecutionRecord('--version', []);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_short_version_command(): void
    {
        $this->renderer->expects($this->once())
            ->method('renderVersion');

        $record = $this->createExecutionRecord('-v', []);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Argument Passing Tests ====================

    public function test_execute_passes_arguments_and_options_to_parser(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $this->isInstanceOf(StringTypedCollection::class))
            ->willReturn($this->createEmptyParsedDirectiveRecord());

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

        $record = $this->createExecutionRecord('test-concrete', ['John', '--role=admin', '--verbose']);

        $this->service->execute($record);
    }

    // ==================== Laravel Bootstrap Tests ====================

    public function test_execute_boots_laravel_when_directive_requests_it(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = $this->createEmptyParsedDirectiveRecord();

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-laravel', $this->isInstanceOf(StringTypedCollection::class))
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

        $mockBootstrapperContext = $this->createMock(LaravelBootstrapperContext::class);
        $mockBootstrapperContext->expects($this->once())
            ->method('bootstrap');

        $this->service->setLaravelBootstrapper($mockBootstrapperContext);

        $record = $this->createExecutionRecord('test-laravel', []);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Parser Exception Tests ====================

    public function test_execute_captures_parser_invalid_argument_exception_and_returns_invalid_argument(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $errorMessage = 'Not enough arguments (missing: "message")';

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $this->isInstanceOf(StringTypedCollection::class))
            ->willThrowException(new InvalidArgumentException($errorMessage));

        $this->renderer->expects($this->once())
            ->method('renderError')
            ->with($errorMessage);

        $record = $this->createExecutionRecord('test-concrete', []);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_execute_captures_parser_too_many_arguments_exception(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $errorMessage = 'Too many arguments provided';

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $this->isInstanceOf(StringTypedCollection::class))
            ->willThrowException(new InvalidArgumentException($errorMessage));

        $this->renderer->expects($this->once())
            ->method('renderError')
            ->with($errorMessage);

        $record = $this->createExecutionRecord('test-concrete', ['arg1', 'arg2', 'arg3']);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_execute_captures_parser_invalid_signature_format_exception(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $errorMessage = 'Invalid signature format: Required arguments must come before arguments with default values';

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $this->isInstanceOf(StringTypedCollection::class))
            ->willThrowException(new InvalidArgumentException($errorMessage));

        $this->renderer->expects($this->once())
            ->method('renderError')
            ->with($errorMessage);

        $record = $this->createExecutionRecord('test-concrete', []);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_execute_captures_generic_exception_and_returns_failure(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $errorMessage = 'Unexpected database error';

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $this->isInstanceOf(StringTypedCollection::class))
            ->willThrowException(new \RuntimeException($errorMessage));

        $this->renderer->expects($this->once())
            ->method('renderError')
            ->with($errorMessage);

        $record = $this->createExecutionRecord('test-concrete', ['John']);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::FAILURE, $result);
    }
}
