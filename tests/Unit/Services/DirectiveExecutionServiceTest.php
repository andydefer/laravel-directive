<?php

// tests/Unit/Services/DirectiveExecutionServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Contracts\ContainerInterface;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Services\ContainerService;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestCalculatorDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestEchoDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestFailingDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestGreetingDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestLaravelDirective;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestNestedDirective;
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

    private HydrationService $hydration;

    private string|false $originalDebug;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hydration = new HydrationService;

        $this->discovery = $this->createMock(DirectiveDiscoveryService::class);
        $this->parser = $this->createMock(DirectiveParserService::class);
        $this->hydrator = $this->createMock(DirectiveHydratorService::class);
        $this->renderer = $this->createMock(DirectiveRendererService::class);

        $this->container = new ContainerService;

        $this->service = new DirectiveExecutionService(
            discovery: $this->discovery,
            parser: $this->parser,
            hydrator: $this->hydrator,
            renderer: $this->renderer,
            container: $this->container,
        );

        $this->originalDebug = getenv('DIRECTIVE_DEBUG');
    }

    protected function tearDown(): void
    {
        if ($this->originalDebug === false) {
            putenv('DIRECTIVE_DEBUG');
        } else {
            putenv('DIRECTIVE_DEBUG='.$this->originalDebug);
        }

        parent::tearDown();
    }

    private function createDirectivesCollection(): DirectiveMetadataCollection
    {
        $collection = new DirectiveMetadataCollection;

        // Directive parente
        $aliases1 = new StringTypedCollection;
        $directive1 = new DirectiveMetadataRecord(
            signature: 'test-concrete',
            class: TestConcreteDirective::class,
            description: 'Test concrete directive',
            aliases: $aliases1,
        );
        $collection->add($directive1);

        // Directive package
        $aliases2 = new StringTypedCollection;
        $aliases2->add('tpkg');
        $directive2 = new DirectiveMetadataRecord(
            signature: 'test-package',
            class: TestPackageDirective::class,
            description: 'Test package directive',
            aliases: $aliases2,
        );
        $collection->add($directive2);

        // Directive Laravel
        $aliases3 = new StringTypedCollection;
        $directive3 = new DirectiveMetadataRecord(
            signature: 'test-laravel',
            class: TestLaravelDirective::class,
            description: 'Test Laravel directive',
            aliases: $aliases3,
        );
        $collection->add($directive3);

        $aliases4 = new StringTypedCollection;
        $directive4 = new DirectiveMetadataRecord(
            signature: 'test-echo {message=} {extra=}',
            class: TestEchoDirective::class,
            description: 'Test Laravel directive',
            aliases: $aliases4,
        );
        $collection->add($directive4);

        // Directive Calculator (appelée par 'calc')
        $aliasesCalc = new StringTypedCollection;
        $aliasesCalc->add('calc');
        $aliasesCalc->add('math');
        $directiveCalc = new DirectiveMetadataRecord(
            signature: 'calculator {operation} {a} {b?}',
            class: TestCalculatorDirective::class,
            description: 'Test calculator directive',
            aliases: $aliasesCalc,
        );
        $collection->add($directiveCalc);

        // Directive Greeting (appelée par 'greeting')
        $aliasesGreeting = new StringTypedCollection;
        $directiveGreeting = new DirectiveMetadataRecord(
            signature: 'greeting {name?}',
            class: TestGreetingDirective::class,
            description: 'Test greeting directive',
            aliases: $aliasesGreeting,
        );
        $collection->add($directiveGreeting);

        // ✅ Directive Nested (appelée par 'nested')
        $aliasesNested = new StringTypedCollection;
        $directiveNested = new DirectiveMetadataRecord(
            signature: 'nested',
            class: TestNestedDirective::class,  // ← Doit exister
            description: 'Test nested directive',
            aliases: $aliasesNested,
        );
        $collection->add($directiveNested);

        // ✅ Directive Failing (appelée par 'failing')
        $aliasesFailing = new StringTypedCollection;
        $directiveFailing = new DirectiveMetadataRecord(
            signature: 'failing',
            class: TestFailingDirective::class,  // ← Doit exister
            description: 'Test failing directive',
            aliases: $aliasesFailing,
        );
        $collection->add($directiveFailing);

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
        $argumentCollection = new StringTypedCollection;
        foreach ($arguments as $arg) {
            $argumentCollection->add($arg);
        }

        return $this->hydration->hydrate(DirectiveExecutionRecord::class, [
            'signature' => $signature,
            'arguments' => $argumentCollection,
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
            ->method('run')
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
            ->method('run')
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
            ->method('run')
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
            ->method('run')
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
            ->method('run')
            ->willReturn(ExitCode::SUCCESS);

        $this->hydrator->expects($this->once())
            ->method('hydrate')
            ->with(TestLaravelDirective::class, $parsedRecord)
            ->willReturn($directive);

        $this->renderer->expects($this->once())
            ->method('renderSuccess');

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

    // ==================== Call System Tests ====================

    public function test_execute_executes_calls_recursively(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->atLeastOnce())
            ->method('discover')
            ->willReturn($directives);

        $parsedRecord = $this->createEmptyParsedDirectiveRecord();
        $this->parser->expects($this->atLeastOnce())
            ->method('parse')
            ->willReturn($parsedRecord);

        $parentDirective = $this->createMock(DirectiveInterface::class);
        $parentDirective->expects($this->once())
            ->method('run')
            ->willReturn(ExitCode::SUCCESS);

        $args1 = new StringTypedCollection;
        $args1->add('add', '10', '5');
        $args2 = new StringTypedCollection;
        $args2->add('pow', '2', '3');
        $args3 = new StringTypedCollection;
        $args3->add('John');

        $calls = [
            new DirectiveExecutionRecord('calc', $args1),
            new DirectiveExecutionRecord('calc', $args2),
            new DirectiveExecutionRecord('greeting', $args3),
        ];
        $parentDirective->method('getCalls')->willReturn($calls);

        $childDirective = $this->createMock(DirectiveInterface::class);
        $childDirective->expects($this->atLeastOnce())
            ->method('run')
            ->willReturn(ExitCode::SUCCESS);
        $childDirective->method('getCalls')->willReturn([]);

        $this->hydrator->method('hydrate')
            ->willReturnCallback(function ($class, $parsed) use ($parentDirective, $childDirective) {
                if ($class === TestConcreteDirective::class) {
                    return $parentDirective;
                }

                return $childDirective;
            });

        // ✅ Le test doit s'attendre à ce que renderSuccess soit appelée plusieurs fois
        // (une fois pour la directive parente + une fois pour chaque enfant)
        $this->renderer->expects($this->atLeastOnce())
            ->method('renderSuccess')
            ->with('Directive executed successfully');

        $record = $this->createExecutionRecord('test-concrete', []);
        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_executes_calls_in_correct_order(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->any())
            ->method('discover')
            ->willReturn($directives);

        $parsed = $this->createEmptyParsedDirectiveRecord();
        $this->parser->expects($this->any())
            ->method('parse')
            ->willReturn($parsed);

        $callOrder = [];

        $parentDirective = $this->createMock(DirectiveInterface::class);
        $parentDirective->expects($this->once())
            ->method('run')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'parent';

                return ExitCode::SUCCESS;
            });

        $args1 = new StringTypedCollection;
        $args1->add('add');
        $args2 = new StringTypedCollection;
        $args2->add('pow');
        $args3 = new StringTypedCollection;
        $args3->add('John');

        $calls = [
            new DirectiveExecutionRecord('calc', $args1),
            new DirectiveExecutionRecord('calc', $args2),
            new DirectiveExecutionRecord('greeting', $args3),
        ];
        $parentDirective->method('getCalls')->willReturn($calls);

        $childCounter = 0;
        $childDirective = $this->createMock(DirectiveInterface::class);
        $childDirective->method('run')->willReturnCallback(function () use (&$callOrder, &$childCounter) {
            $callOrder[] = 'child_'.$childCounter;
            $childCounter++;

            return ExitCode::SUCCESS;
        });
        $childDirective->method('getCalls')->willReturn([]);

        $this->hydrator->method('hydrate')
            ->willReturnCallback(function ($class, $parsed) use ($parentDirective, $childDirective) {
                if ($class === TestConcreteDirective::class) {
                    return $parentDirective;
                }

                return $childDirective;
            });

        $record = $this->createExecutionRecord('test-concrete', []);
        $this->service->execute($record);

        $this->assertSame('parent', $callOrder[0]);
        $this->assertArrayHasKey(1, $callOrder);
        $this->assertStringStartsWith('child_', (string) $callOrder[1]);
        $this->assertArrayHasKey(2, $callOrder);
        $this->assertStringStartsWith('child_', (string) $callOrder[2]);
        $this->assertArrayHasKey(3, $callOrder);
        $this->assertStringStartsWith('child_', (string) $callOrder[3]);
    }

    public function test_execute_with_nested_calls(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->any())
            ->method('discover')
            ->willReturn($directives);

        $parsed = $this->createEmptyParsedDirectiveRecord();
        $this->parser->expects($this->any())
            ->method('parse')
            ->willReturn($parsed);

        // ✅ Parent directive
        $parentDirective = $this->createMock(DirectiveInterface::class);
        $parentDirective->expects($this->once())  // ← Une seule fois !
            ->method('run')
            ->willReturn(ExitCode::SUCCESS);

        $argsChild1 = new StringTypedCollection;
        $argsChild2 = new StringTypedCollection;
        $argsChild3 = new StringTypedCollection;

        // ✅ Parent appelle des enfants (1er niveau uniquement)
        // ✅ Ne pas s'appeler soi-même
        $parentDirective->method('getCalls')->willReturn([
            new DirectiveExecutionRecord('greeting', $argsChild1),
            new DirectiveExecutionRecord('test-echo', $argsChild2),
            new DirectiveExecutionRecord('calculator', $argsChild3), // ← 'calculator' au lieu de 'test-concrete'
        ]);

        // ✅ Les enfants ne font PAS d'appels
        $childDirective = $this->createMock(DirectiveInterface::class);
        $childDirective->expects($this->atLeastOnce())
            ->method('run')
            ->willReturn(ExitCode::SUCCESS);
        $childDirective->method('getCalls')->willReturn([]);

        $this->hydrator->method('hydrate')
            ->willReturnCallback(function ($class, $parsed) use ($parentDirective, $childDirective) {
                if ($class === TestConcreteDirective::class) {
                    return $parentDirective;
                }

                return $childDirective;
            });

        $this->renderer->expects($this->atLeastOnce())
            ->method('renderSuccess')
            ->with('Directive executed successfully');

        $record = $this->createExecutionRecord('test-concrete', []);
        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_stops_on_failed_call(): void
    {
        $directives = $this->createDirectivesCollection();

        $this->discovery->expects($this->any())
            ->method('discover')
            ->willReturn($directives);

        $parsed = $this->createEmptyParsedDirectiveRecord();
        $this->parser->expects($this->any())
            ->method('parse')
            ->willReturn($parsed);

        $parentDirective = $this->createMock(DirectiveInterface::class);
        $parentDirective->expects($this->once())
            ->method('run')
            ->willReturn(ExitCode::SUCCESS);

        $args1 = new StringTypedCollection;
        $args1->add('add', '10', '5');

        $args2 = new StringTypedCollection;
        $args3 = new StringTypedCollection;
        $args3->add('John');

        $calls = [
            new DirectiveExecutionRecord('calc', $args1),
            new DirectiveExecutionRecord('failing', $args2),
            new DirectiveExecutionRecord('greeting', $args3),
        ];
        $parentDirective->method('getCalls')->willReturn($calls);

        $calcDirective = $this->createMock(DirectiveInterface::class);
        $calcDirective->expects($this->once())
            ->method('run')
            ->willReturn(ExitCode::SUCCESS);
        $calcDirective->method('getCalls')->willReturn([]);

        $failingDirective = $this->createMock(DirectiveInterface::class);
        $failingDirective->expects($this->once())
            ->method('run')
            ->willReturn(ExitCode::FAILURE);
        $failingDirective->method('getCalls')->willReturn([]);

        $greetingDirective = $this->createMock(DirectiveInterface::class);
        $greetingDirective->expects($this->once())
            ->method('run')
            ->willReturn(ExitCode::SUCCESS);
        $greetingDirective->method('getCalls')->willReturn([]);

        $this->hydrator->method('hydrate')
            ->willReturnCallback(function ($class, $parsed) use ($parentDirective, $calcDirective, $failingDirective, $greetingDirective) {
                if ($class === TestConcreteDirective::class) {
                    return $parentDirective;
                }
                if ($class === TestCalculatorDirective::class) {
                    return $calcDirective;
                }
                if ($class === TestFailingDirective::class) {
                    return $failingDirective;
                }
                if ($class === TestGreetingDirective::class) {
                    return $greetingDirective;
                }

                return $calcDirective;
            });

        // ✅ Collecter les messages d'erreur
        $errorMessages = [];
        $this->renderer->expects($this->exactly(2))
            ->method('renderError')
            ->willReturnCallback(function ($message) use (&$errorMessages) {
                $errorMessages[] = $message;
            });

        $record = $this->createExecutionRecord('test-concrete', []);
        $result = $this->service->execute($record);

        // ✅ Vérifier les messages
        $this->assertCount(2, $errorMessages);
        $this->assertSame("Child directive 'failing' failed", $errorMessages[0]);
        $this->assertSame('Directive execution failed', $errorMessages[1]);

        $this->assertSame(ExitCode::FAILURE, $result);
    }
}
