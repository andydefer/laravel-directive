<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Testing;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Testing\TestDirectiveRegistry;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestCalculatorDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class TestDirectiveRegistryTest extends UnitTestCase
{
    private TestDirectiveRegistry $registry;

    private DirectiveInteractionService&MockObject $interaction;

    private SignatureValidationService&MockObject $signatureValidator;

    private DirectiveNamingService&MockObject $namingService;

    private LaravelBootstrapper&MockObject $laravelBootstrapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new TestDirectiveRegistry;
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->signatureValidator = $this->createMock(SignatureValidationService::class);
        $this->namingService = $this->createMock(DirectiveNamingService::class);
        $this->laravelBootstrapper = $this->createMock(LaravelBootstrapper::class);

        $this->registry->setInteraction($this->interaction);
        $this->registry->setSignatureValidator($this->signatureValidator);
        $this->registry->setNamingService($this->namingService);
        $this->registry->setLaravelBootstrapper($this->laravelBootstrapper);
    }

    public function test_register_directive_by_signature(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);

        $this->registry->register('calculator', $directive);

        $retrieved = $this->registry->getDirective('calculator');
        $this->assertSame($directive, $retrieved);
    }

    public function test_register_directive_with_alias(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);

        $this->registry->register('calculator', $directive);

        $retrieved = $this->registry->getDirective('calc');
        $this->assertSame($directive, $retrieved);
    }

    public function test_register_directive_by_class(): void
    {
        $directive = $this->registry->registerByClass(TestCalculatorDirective::class);

        $this->assertInstanceOf(TestCalculatorDirective::class, $directive);

        // Utiliser la signature EXACTE retournée par getSignature()
        $exactSignature = $directive->getSignature();
        $retrieved = $this->registry->getDirective($exactSignature);

        $this->assertSame($directive, $retrieved);
    }

    public function test_register_directive_by_class_with_custom_constructor_args(): void
    {
        $customInteraction = $this->createMock(DirectiveInteractionService::class);

        $directive = $this->registry->registerByClass(
            TestCalculatorDirective::class,
            [$customInteraction]
        );

        $this->assertInstanceOf(TestCalculatorDirective::class, $directive);
    }

    public function test_register_directive_by_class_throws_exception_when_interaction_missing(): void
    {
        $registry = new TestDirectiveRegistry;
        // Ne pas setInteraction()

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DirectiveInteractionService not set');

        $registry->registerByClass(TestCalculatorDirective::class);
    }

    public function test_load_returns_typed_collection_of_metadata(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registry->register('calculator', $directive);

        $collection = $this->registry->load();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());
    }

    public function test_get_directive_returns_null_for_non_existent(): void
    {
        $retrieved = $this->registry->getDirective('non-existent');
        $this->assertNull($retrieved);
    }

    public function test_clear_removes_all_directives(): void
    {
        $directive = new TestCalculatorDirective($this->interaction);
        $this->registry->register('calculator', $directive);

        $this->assertNotNull($this->registry->getDirective('calculator'));

        $this->registry->clear();

        $this->assertNull($this->registry->getDirective('calculator'));
    }

    public function test_register_multiple_directives(): void
    {
        $directive1 = new TestCalculatorDirective($this->interaction);
        $directive2 = new TestCalculatorDirective($this->interaction);

        $this->registry->register('calc1', $directive1);
        $this->registry->register('calc2', $directive2);

        $this->assertSame($directive1, $this->registry->getDirective('calc1'));
        $this->assertSame($directive2, $this->registry->getDirective('calc2'));
    }

    public function test_register_directive_with_multiple_aliases(): void
    {
        $directive = $this->createMock(TestCalculatorDirective::class);
        $directive->method('getSignature')->willReturn('test-cmd');

        $aliases = new StringTypedCollection;
        $aliases->add('t1');
        $aliases->add('t2');
        $directive->method('getAliases')->willReturn($aliases);

        $this->registry->register('test-cmd', $directive);

        $this->assertSame($directive, $this->registry->getDirective('test-cmd'));
        $this->assertSame($directive, $this->registry->getDirective('t1'));
        $this->assertSame($directive, $this->registry->getDirective('t2'));
    }
}
