<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * Contract for registering directives from packages or external sources.
 *
 * Usage in package ServiceProvider:
 *
 * ```php
 * use AndyDefer\Directive\Contracts\DirectiveRegistrarInterface;
 *
 * public function boot(): void
 * {
 *     $classes = new StringTypedCollection();
 *     $classes->add(MyDirective::class, AnotherDirective::class);
 *
 *     app(DirectiveRegistrarInterface::class)->register($classes);
 * }
 * ```
 */
interface DirectiveRegistrarInterface
{
    /**
     * Register directive classes.
     *
     * @param  StringTypedCollection  $directiveClasses  Collection of directive class names
     * @return self Returns the instance for method chaining
     */
    public function register(StringTypedCollection $directiveClasses): self;

    /**
     * Get all registered directive classes.
     *
     * @return StringTypedCollection Collection of class names
     */
    public function getRegistered(): StringTypedCollection;

    /**
     * Check if a directive class is registered.
     *
     * @param  string  $directiveClass  The directive class name
     * @return bool True if registered, false otherwise
     */
    public function isRegistered(string $directiveClass): bool;
}
