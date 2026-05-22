<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Registrars;

use AndyDefer\Directive\Contracts\DirectiveRegistrarInterface;
use AndyDefer\Directive\Tests\Fixtures\Directives\TestPackageDirective;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class TestPackageRegistrar implements DirectiveRegistrarInterface
{
    public function register(StringTypedCollection $directiveClasses): self
    {
        $directiveClasses->add(TestPackageDirective::class);

        return $this;
    }

    public function getRegistered(): StringTypedCollection
    {
        $classes = new StringTypedCollection;
        $classes->add(TestPackageDirective::class);

        return $classes;
    }

    public function isRegistered(string $directiveClass): bool
    {
        return $directiveClass === TestPackageDirective::class;
    }
}
