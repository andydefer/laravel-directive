<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\DirectiveRegistrar;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\Directive\Tests\Fixtures\RegisteredDirectives\TestPackageDirective;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class DirectiveRegistrarTest extends UnitTestCase
{
    private DirectiveRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registrar = new DirectiveRegistrar;
    }

    public function test_register_adds_directive_classes(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestPackageDirective::class);

        $result = $this->registrar->register($classes);

        $this->assertSame($this->registrar, $result);
        $this->assertTrue($this->registrar->isRegistered(TestPackageDirective::class));
        $this->assertSame(1, $this->registrar->count());
    }

    public function test_register_adds_multiple_directive_classes(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestPackageDirective::class);
        $classes->add(TestConcreteDirective::class);

        $this->registrar->register($classes);

        $this->assertSame(2, $this->registrar->count());
        $this->assertTrue($this->registrar->isRegistered(TestPackageDirective::class));
        $this->assertTrue($this->registrar->isRegistered(TestConcreteDirective::class));
    }

    public function test_register_ignores_nonexistent_classes(): void
    {
        $classes = new StringTypedCollection;
        $classes->add('NonexistentClass');

        $this->registrar->register($classes);

        $this->assertSame(0, $this->registrar->count());
    }

    public function test_register_ignores_classes_not_implementing_directive_interface(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(\stdClass::class);

        $this->registrar->register($classes);

        $this->assertSame(0, $this->registrar->count());
    }

    public function test_register_ignores_duplicate_classes(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestPackageDirective::class, TestPackageDirective::class);

        $this->registrar->register($classes);

        $this->assertSame(1, $this->registrar->count());
    }

    public function test_get_registered_returns_collection_of_strings(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestPackageDirective::class);
        $this->registrar->register($classes);

        $registered = $this->registrar->getRegistered();

        $this->assertInstanceOf(StringTypedCollection::class, $registered);
        $this->assertTrue($registered->contains(TestPackageDirective::class));
    }

    public function test_is_registered_returns_false_for_unregistered_class(): void
    {
        $this->assertFalse($this->registrar->isRegistered(TestPackageDirective::class));
    }

    public function test_clear_removes_all_registered_directives(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestPackageDirective::class);
        $this->registrar->register($classes);
        $this->assertSame(1, $this->registrar->count());

        $result = $this->registrar->clear();

        $this->assertSame($this->registrar, $result);
        $this->assertSame(0, $this->registrar->count());
        $this->assertFalse($this->registrar->isRegistered(TestPackageDirective::class));
    }

    public function test_register_returns_self_for_chaining(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestPackageDirective::class);

        $result = $this->registrar->register($classes);

        $this->assertSame($this->registrar, $result);
    }

    public function test_count_returns_zero_when_no_directives_registered(): void
    {
        $this->assertSame(0, $this->registrar->count());
    }

    public function test_find_returns_class_by_signature(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestConcreteDirective::class);
        $this->registrar->register($classes);

        $result = $this->registrar->find('test-concrete');

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->contains(TestConcreteDirective::class));
    }

    public function test_find_returns_class_by_alias(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestPackageDirective::class);
        $this->registrar->register($classes);

        $result = $this->registrar->find('tpkg');

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->contains(TestPackageDirective::class));
    }

    public function test_find_returns_empty_collection_when_not_found(): void
    {
        $result = $this->registrar->find('unknown');

        $this->assertTrue($result->isEmpty());
    }

    public function test_has_conflict_returns_false_for_unique_directive(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestConcreteDirective::class);
        $this->registrar->register($classes);

        $hasConflict = $this->registrar->hasConflict('test-concrete');

        $this->assertFalse($hasConflict);
    }

    public function test_get_all_directives_metadata_returns_collection(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestConcreteDirective::class);
        $this->registrar->register($classes);

        $metadata = $this->registrar->getAllDirectivesMetadata();

        $this->assertSame(1, $metadata->count());
    }

    public function test_get_signature_map_returns_array(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestConcreteDirective::class);
        $this->registrar->register($classes);

        $map = $this->registrar->getSignatureMap();

        $this->assertIsArray($map);
    }

    public function test_get_alias_map_returns_array(): void
    {
        $classes = new StringTypedCollection;
        $classes->add(TestPackageDirective::class);
        $this->registrar->register($classes);

        $map = $this->registrar->getAliasMap();

        $this->assertIsArray($map);
    }
}
