<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Contracts\DirectiveRegistrarInterface;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * Service responsible for registering directive classes from external packages.
 */
final class DirectiveRegistrar implements DirectiveRegistrarInterface
{
    private StringTypedCollection $registeredDirectives;

    public function __construct()
    {
        $this->registeredDirectives = new StringTypedCollection();
    }

    public function register(StringTypedCollection $directiveClasses): self
    {
        foreach ($directiveClasses as $class) {
            if (!is_string($class)) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, DirectiveInterface::class)) {
                continue;
            }

            if (!$this->registeredDirectives->contains($class)) {
                $this->registeredDirectives->add($class);
            }
        }

        return $this;
    }

    public function getRegistered(): StringTypedCollection
    {
        return $this->registeredDirectives;
    }

    public function isRegistered(string $directiveClass): bool
    {
        return $this->registeredDirectives->contains($directiveClass);
    }

    /**
     * Clear all registered directives.
     *
     * @return self Returns the instance for method chaining
     */
    public function clear(): self
    {
        $this->registeredDirectives = new StringTypedCollection();
        return $this;
    }

    /**
     * Get the count of registered directives.
     *
     * @return int Number of registered directives
     */
    public function count(): int
    {
        return $this->registeredDirectives->count();
    }
}
