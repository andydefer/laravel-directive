<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Testing;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Testing\TestDirectiveRegistry;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestCalculatorDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class TestDirectiveRegistryTest extends UnitTestCase
{
    private TestDirectiveRegistry $registry;

    private DirectiveInteractionService $interaction;

    private DirectiveContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new TestDirectiveRegistry;
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->context = $this->createMock(DirectiveContext::class);
    }

    private function createTestDirective(): TestCalculatorDirective
    {
        return new TestCalculatorDirective($this->context, $this->interaction);
    }

    public function test_register_directive(): void
    {
        $directive = $this->createTestDirective();
        $this->registry->register($directive);

        $retrieved = $this->registry->getDirective(TestCalculatorDirective::class);
        $this->assertSame($directive, $retrieved);
    }

    public function test_register_directive_prevents_duplicates(): void
    {
        $directive1 = $this->createTestDirective();
        $directive2 = $this->createTestDirective();

        $this->registry->register($directive1);
        $this->registry->register($directive2);

        $retrieved = $this->registry->getDirective(TestCalculatorDirective::class);
        $this->assertSame($directive1, $retrieved);
        $this->assertNotSame($directive2, $retrieved);
    }

    public function test_load_returns_typed_collection_of_metadata(): void
    {
        $directive = $this->createTestDirective();
        $this->registry->register($directive);

        $collection = $this->registry->load();

        $this->assertInstanceOf(DirectiveMetadataCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());
    }

    public function test_load_returns_correct_metadata(): void
    {
        $directive = $this->createTestDirective();
        $this->registry->register($directive);

        $collection = $this->registry->load();
        $items = $collection->toArray();
        $metadata = $items[0];

        $this->assertSame('calculator {operation} {a} {b?}', $metadata->signature);
        $this->assertSame(TestCalculatorDirective::class, $metadata->class);
        $this->assertSame('Test calculator directive for testing arithmetic operations', $metadata->description);
    }

    public function test_get_directive_by_class_name(): void
    {
        $directive = $this->createTestDirective();
        $this->registry->register($directive);

        $retrieved = $this->registry->getDirective(TestCalculatorDirective::class);
        $this->assertSame($directive, $retrieved);
    }

    public function test_get_directive_returns_null_for_non_existent(): void
    {
        $retrieved = $this->registry->getDirective('NonExistentClass');
        $this->assertNull($retrieved);
    }

    public function test_has_directive_returns_true_when_exists(): void
    {
        $directive = $this->createTestDirective();
        $this->registry->register($directive);

        $this->assertTrue($this->registry->hasDirective(TestCalculatorDirective::class));
    }

    public function test_has_directive_returns_false_when_not_exists(): void
    {
        $this->assertFalse($this->registry->hasDirective('NonExistentClass'));
    }

    public function test_clear_removes_all_directives(): void
    {
        $directive = $this->createTestDirective();
        $this->registry->register($directive);

        $this->assertNotNull($this->registry->getDirective(TestCalculatorDirective::class));

        $this->registry->clear();

        $this->assertNull($this->registry->getDirective(TestCalculatorDirective::class));
        $this->assertEmpty($this->registry->getAllDirectives());
    }

    public function test_register_multiple_directives(): void
    {
        $directive1 = $this->createTestDirective();

        // Créer une classe différente pour la deuxième directive
        $directive2 = $this->createMock(TestCalculatorDirective::class);
        $directive2->method('getSignature')->willReturn('other-cmd');
        $directive2->method('getAliases')->willReturn(new StringTypedCollection);
        $directive2->method('getDescription')->willReturn('Other command');

        $this->registry->register($directive1);
        $this->registry->register($directive2);

        $this->assertSame($directive1, $this->registry->getDirective(TestCalculatorDirective::class));
        // Pour la deuxième directive, on ne peut pas la récupérer par classe car c'est un mock
        $allDirectives = $this->registry->getAllDirectives();
        $this->assertCount(2, $allDirectives);
    }

    public function test_get_all_directives_returns_all_registered_directives(): void
    {
        $directive = $this->createTestDirective();
        $this->registry->register($directive);

        $allDirectives = $this->registry->getAllDirectives();

        $this->assertCount(1, $allDirectives);
        $this->assertArrayHasKey(TestCalculatorDirective::class, $allDirectives);
        $this->assertSame($directive, $allDirectives[TestCalculatorDirective::class]);
    }

    public function test_register_directives_batch(): void
    {
        $directive1 = $this->createTestDirective();

        $directive2 = $this->createMock(TestCalculatorDirective::class);
        $directive2->method('getSignature')->willReturn('other-cmd');
        $directive2->method('getAliases')->willReturn(new StringTypedCollection);
        $directive2->method('getDescription')->willReturn('Other command');

        $this->registry->registerAll([$directive1, $directive2]);

        $this->assertSame($directive1, $this->registry->getDirective(TestCalculatorDirective::class));
        $allDirectives = $this->registry->getAllDirectives();
        $this->assertCount(2, $allDirectives);
    }
}
