<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
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
use AndyDefer\DomainStructures\Collections\Utility\ScalarTypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
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
    protected LaravelBootstrapper $laravelBootstrapper;
    private string|false $originalDebug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discovery = $this->createMock(DirectiveDiscoveryService::class);
        $this->parser = $this->createMock(DirectiveParserService::class);
        $this->hydrator = $this->createMock(DirectiveHydratorService::class);
        $this->renderer = $this->createMock(DirectiveRendererService::class);
        $this->laravelBootstrapper = new LaravelBootstrapper();

        $this->service = new DirectiveExecutionService(
            discovery: $this->discovery,
            parser: $this->parser,
            hydrator: $this->hydrator,
            renderer: $this->renderer,
        );
        $this->service->setLaravelBootstrapper($this->laravelBootstrapper);

        $this->originalDebug = getenv('DIRECTIVE_DEBUG');
    }

    protected function tearDown(): void
    {
        if ($this->originalDebug === false) {
            putenv('DIRECTIVE_DEBUG');
        } else {
            putenv('DIRECTIVE_DEBUG=' . $this->originalDebug);
        }

        $this->laravelBootstrapper->reset();
        parent::tearDown();
    }

    private function createDirectivesCollection(): DirectiveMetadataCollection
    {
        $collection = new DirectiveMetadataCollection();

        $aliases1 = new StringTypedCollection();
        $directive1 = new DirectiveMetadataRecord(
            signature: 'test-concrete',
            class: TestConcreteDirective::class,
            description: 'Test concrete directive',
            aliases: $aliases1,
        );
        $collection->add($directive1);

        $aliases2 = new StringTypedCollection();
        $aliases2->add('tpkg');
        $directive2 = new DirectiveMetadataRecord(
            signature: 'test-package',
            class: TestPackageDirective::class,
            description: 'Test package directive',
            aliases: $aliases2,
        );
        $collection->add($directive2);

        $aliases3 = new StringTypedCollection();
        $directive3 = new DirectiveMetadataRecord(
            signature: 'test-laravel',
            class: TestLaravelDirective::class,
            description: 'Test Laravel directive',
            aliases: $aliases3,
        );
        $collection->add($directive3);

        return $collection;
    }

    // ==================== Not Found Tests ====================

    public function test_execute_returns_not_found_when_directive_does_not_exist(): void
    {
        // Arrange: Empty directives collection
        $directives = new DirectiveMetadataCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $this->renderer->expects($this->once())
            ->method('renderNotFound')
            ->with('unknown-cmd');

        $record = DirectiveExecutionRecord::from([
            'signature' => 'unknown-cmd',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }

    // ==================== Success/Failure Tests ====================

    public function test_execute_returns_success_when_directive_exists(): void
    {
        // Arrange
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new ScalarTypedCollection(),
            options: new ScalarTypedCollection(),
        );

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

        $record = DirectiveExecutionRecord::from([
            'signature' => 'test-concrete',
            'arguments' => ['John'],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_failure_when_directive_fails(): void
    {
        // Arrange
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new ScalarTypedCollection(),
            options: new ScalarTypedCollection(),
        );

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

        $record = DirectiveExecutionRecord::from([
            'signature' => 'test-concrete',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_execute_handles_directive_by_alias(): void
    {
        // Arrange
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new ScalarTypedCollection(),
            options: new ScalarTypedCollection(),
        );

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

        $record = DirectiveExecutionRecord::from([
            'signature' => 'tpkg',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Built-in Commands Tests ====================

    public function test_execute_handles_help_command(): void
    {
        // Arrange
        $this->renderer->expects($this->once())
            ->method('renderHelp');

        $record = DirectiveExecutionRecord::from([
            'signature' => '--help',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_short_help_command(): void
    {
        // Arrange
        $this->renderer->expects($this->once())
            ->method('renderHelp');

        $record = DirectiveExecutionRecord::from([
            'signature' => '-h',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_list_command(): void
    {
        // Arrange
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $this->renderer->expects($this->once())
            ->method('renderList')
            ->with($directives);

        $record = DirectiveExecutionRecord::from([
            'signature' => '--list',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_short_list_command(): void
    {
        // Arrange
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $this->renderer->expects($this->once())
            ->method('renderList')
            ->with($directives);

        $record = DirectiveExecutionRecord::from([
            'signature' => '-l',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_version_command(): void
    {
        // Arrange
        $this->renderer->expects($this->once())
            ->method('renderVersion');

        $record = DirectiveExecutionRecord::from([
            'signature' => '--version',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_handles_short_version_command(): void
    {
        // Arrange
        $this->renderer->expects($this->once())
            ->method('renderVersion');

        $record = DirectiveExecutionRecord::from([
            'signature' => '-v',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Argument Passing Tests ====================

    public function test_execute_passes_arguments_and_options_to_parser(): void
    {
        // Arrange
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $this->parser->expects($this->once())
            ->method('parse')
            ->with('test-concrete', $this->isInstanceOf(StringTypedCollection::class))
            ->willReturn(new ParsedDirectiveRecord(
                arguments: new ScalarTypedCollection(),
                options: new ScalarTypedCollection(),
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

        $record = DirectiveExecutionRecord::from([
            'signature' => 'test-concrete',
            'arguments' => ['John', '--role=admin', '--verbose'],
        ]);

        // Act
        $this->service->execute($record);
    }

    // ==================== Laravel Bootstrap Tests ====================

    public function test_execute_boots_laravel_when_directive_requests_it(): void
    {
        // Arrange
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->once())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new ScalarTypedCollection(),
            options: new ScalarTypedCollection(),
        );

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

        $mockBootstrapper = $this->createMock(LaravelBootstrapper::class);
        $mockBootstrapper->expects($this->once())
            ->method('bootstrap');

        $this->service->setLaravelBootstrapper($mockBootstrapper);

        $record = DirectiveExecutionRecord::from([
            'signature' => 'test-laravel',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    // ==================== Parser Exception Tests ====================

    public function test_execute_captures_parser_invalid_argument_exception_and_returns_invalid_argument(): void
    {
        // Arrange
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

        $record = DirectiveExecutionRecord::from([
            'signature' => 'test-concrete',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_execute_captures_parser_too_many_arguments_exception(): void
    {
        // Arrange
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

        $record = DirectiveExecutionRecord::from([
            'signature' => 'test-concrete',
            'arguments' => ['arg1', 'arg2', 'arg3'],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_execute_captures_parser_invalid_signature_format_exception(): void
    {
        // Arrange
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

        $record = DirectiveExecutionRecord::from([
            'signature' => 'test-concrete',
            'arguments' => [],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_execute_captures_generic_exception_and_returns_failure(): void
    {
        // Arrange
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

        $record = DirectiveExecutionRecord::from([
            'signature' => 'test-concrete',
            'arguments' => ['John'],
        ]);

        // Act
        $result = $this->service->execute($record);

        // Assert
        $this->assertSame(ExitCode::FAILURE, $result);
    }
}
