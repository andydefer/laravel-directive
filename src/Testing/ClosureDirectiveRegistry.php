<?php

// src/Testing/ClosureDirectiveRegistry.php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

final class ClosureDirectiveRegistry
{
    /**
     * Storage for closure directives indexed by directive name.
     *
     * @var array<string, ClosureDirective>
     */
    private array $directives = [];

    /**
     * Register a closure directive.
     *
     * @param  ClosureDirective  $directive  The directive to register
     */
    public function register(ClosureDirective $directive): void
    {
        $signature = $directive->getSignature();

        // Extraire le nom de la directive (premier mot avant les paramètres)
        $name = explode(' ', $signature)[0];
        $name = explode('{', $name)[0];

        $this->directives[$name] = $directive;
    }

    /**
     * Get a closure directive by its name.
     *
     * @param  string  $name  The directive name
     * @return ClosureDirective|null The directive or null if not found
     */
    public function get(string $name): ?ClosureDirective
    {
        return $this->directives[$name] ?? null;
    }

    /**
     * Check if a closure directive exists.
     *
     * @param  string  $name  The directive name
     * @return bool True if exists
     */
    public function has(string $name): bool
    {
        return isset($this->directives[$name]);
    }

    /**
     * Clear all registered closure directives.
     */
    public function clear(): void
    {
        $this->directives = [];
    }

    /**
     * Get all registered closure directives.
     *
     * @return array<string, ClosureDirective>
     */
    public function getAll(): array
    {
        return $this->directives;
    }
}
