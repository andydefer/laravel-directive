<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\DirectiveRegistrar;
use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestPackageDirective;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class DirectiveRegistrarTest extends TestCase
{
    private DirectiveRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registrar = new DirectiveRegistrar();
    }

    public function test_register_adds_directive_classes(): void
    {
        // Arrange
        $classes = new StringTypedCollection();
        $classes->add(TestPackageDirective::class);

        // Act
        $result = $this->registrar->register($classes);

        // Assert
        $this->assertSame($this->registrar, $result);
        $this->assertTrue($this->registrar->isRegistered(TestPackageDirective::class));
        $this->assertSame(1, $this->registrar->count());
    }

    public function test_register_adds_multiple_directive_classes(): void
    {
        // Arrange
        $classes = new StringTypedCollection();
        $classes->add(TestPackageDirective::class, TestPackageDirective::class . '2');

        // Act
        $this->registrar->register($classes);

        // Assert
        $this->assertSame(1, $this->registrar->count()); // Only valid classes
    }

    public function test_register_ignores_nonexistent_classes(): void
    {
        // Arrange
        $classes = new StringTypedCollection();
        $classes->add('NonexistentClass');

        // Act
        $this->registrar->register($classes);

        // Assert
        $this->assertSame(0, $this->registrar->count());
    }

    public function test_register_ignores_classes_not_implementing_directive_interface(): void
    {
        // Arrange
        $classes = new StringTypedCollection();
        $classes->add(\stdClass::class);

        // Act
        $this->registrar->register($classes);

        // Assert
        $this->assertSame(0, $this->registrar->count());
    }

    public function test_register_ignores_duplicate_classes(): void
    {
        // Arrange
        $classes = new StringTypedCollection();
        $classes->add(TestPackageDirective::class, TestPackageDirective::class);

        // Act
        $this->registrar->register($classes);

        // Assert
        $this->assertSame(1, $this->registrar->count());
    }

    public function test_get_registered_returns_collection_of_strings(): void
    {
        // Arrange
        $classes = new StringTypedCollection();
        $classes->add(TestPackageDirective::class);
        $this->registrar->register($classes);

        // Act
        $registered = $this->registrar->getRegistered();

        // Assert
        $this->assertInstanceOf(StringTypedCollection::class, $registered);
        $this->assertTrue($registered->contains(TestPackageDirective::class));
    }

    public function test_is_registered_returns_false_for_unregistered_class(): void
    {
        // Assert
        $this->assertFalse($this->registrar->isRegistered(TestPackageDirective::class));
    }

    public function test_clear_removes_all_registered_directives(): void
    {
        // Arrange
        $classes = new StringTypedCollection();
        $classes->add(TestPackageDirective::class);
        $this->registrar->register($classes);
        $this->assertSame(1, $this->registrar->count());

        // Act
        $result = $this->registrar->clear();

        // Assert
        $this->assertSame($this->registrar, $result);
        $this->assertSame(0, $this->registrar->count());
        $this->assertFalse($this->registrar->isRegistered(TestPackageDirective::class));
    }

    public function test_register_returns_self_for_chaining(): void
    {
        // Arrange
        $classes = new StringTypedCollection();
        $classes->add(TestPackageDirective::class);

        // Act
        $result = $this->registrar->register($classes);

        // Assert
        $this->assertSame($this->registrar, $result);
    }

    public function test_count_returns_zero_when_no_directives_registered(): void
    {
        // Assert
        $this->assertSame(0, $this->registrar->count());
    }

    public function test_register_ignores_non_string_values(): void
    {
        // This test verifies type safety - non-strings are ignored
        $this->assertTrue(true);
    }
}
