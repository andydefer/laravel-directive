<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Enums\DiscoverySource;

/**
 * Interface for directive discovery services.
 *
 * Provides methods to discover, filter, and manage directive classes
 * from various sources (vendor, workspace, builtin, custom).
 */
interface DirectiveDiscoveryInterface
{
    // ==================== SOURCE MANAGEMENT ====================

    /**
     * Ignore a source.
     *
     * @param  DiscoverySource|string  $source  The source to ignore (vendor, workspace, builtin, custom)
     *
     * @throws \InvalidArgumentException If the source is invalid
     */
    public function ignoreSource(DiscoverySource|string $source): static;

    /**
     * Ignore multiple sources.
     *
     * @param  array<int, DiscoverySource|string>  $sources  The sources to ignore
     *
     * @throws \InvalidArgumentException If any source is invalid
     */
    public function ignoreSources(array $sources): static;

    /**
     * Enable a previously ignored source.
     *
     * @param  DiscoverySource|string  $source  The source to enable
     */
    public function enableSource(DiscoverySource|string $source): static;

    /**
     * Enable multiple sources.
     *
     * @param  array<int, DiscoverySource|string>  $sources  The sources to enable
     */
    public function enableSources(array $sources): static;

    /**
     * Check if a source is ignored.
     *
     * @param  DiscoverySource|string  $source  The source to check
     */
    public function isSourceIgnored(DiscoverySource|string $source): bool;

    // ==================== PATH MANAGEMENT ====================

    /**
     * Ignore a path.
     *
     * @param  string  $path  The path to ignore
     */
    public function ignorePath(string $path): static;

    /**
     * Ignore multiple paths.
     *
     * @param  array<int, string>  $paths  The paths to ignore
     */
    public function ignorePaths(array $paths): static;

    /**
     * Enable a previously ignored path.
     *
     * @param  string  $path  The path to enable
     */
    public function enablePath(string $path): static;

    /**
     * Enable multiple paths.
     *
     * @param  array<int, string>  $paths  The paths to enable
     */
    public function enablePaths(array $paths): static;

    // ==================== DIRECTIVE MANAGEMENT ====================

    /**
     * Ignore a directive by signature.
     *
     * @param  string  $signature  The directive signature to ignore
     */
    public function ignoreDirective(string $signature): static;

    /**
     * Ignore multiple directives by signature.
     *
     * @param  array<int, string>  $signatures  The directive signatures to ignore
     */
    public function ignoreDirectives(array $signatures): static;

    /**
     * Enable a previously ignored directive.
     *
     * @param  string  $signature  The directive signature to enable
     */
    public function enableDirective(string $signature): static;

    /**
     * Enable multiple directives.
     *
     * @param  array<int, string>  $signatures  The directive signatures to enable
     */
    public function enableDirectives(array $signatures): static;

    /**
     * Check if a directive is ignored.
     *
     * @param  string  $signature  The directive signature to check
     */
    public function isDirectiveIgnored(string $signature): bool;

    // ==================== NAMESPACE FILTERING ====================

    /**
     * Add a namespace to the only-namespaces list.
     *
     * @param  string  $namespace  The namespace to include
     */
    public function onlyNamespace(string $namespace): static;

    /**
     * Add multiple namespaces to the only-namespaces list.
     *
     * @param  array<int, string>  $namespaces  The namespaces to include
     */
    public function onlyNamespaces(array $namespaces): static;

    /**
     * Exclude a namespace.
     *
     * @param  string  $namespace  The namespace to exclude
     */
    public function excludeNamespace(string $namespace): static;

    /**
     * Exclude multiple namespaces.
     *
     * @param  array<int, string>  $namespaces  The namespaces to exclude
     */
    public function excludeNamespaces(array $namespaces): static;

    // ==================== PREFIX FILTERING ====================

    /**
     * Add a prefix to the only-prefixes list.
     *
     * @param  string  $prefix  The prefix to include
     */
    public function onlyPrefix(string $prefix): static;

    /**
     * Add multiple prefixes to the only-prefixes list.
     *
     * @param  array<int, string>  $prefixes  The prefixes to include
     */
    public function onlyPrefixes(array $prefixes): static;

    /**
     * Exclude a prefix.
     *
     * @param  string  $prefix  The prefix to exclude
     */
    public function excludePrefix(string $prefix): static;

    /**
     * Exclude multiple prefixes.
     *
     * @param  array<int, string>  $prefixes  The prefixes to exclude
     */
    public function excludePrefixes(array $prefixes): static;

    // ==================== AUTO-DISCOVERY ====================

    /**
     * Disable auto-discovery.
     */
    public function disableAutoDiscovery(): static;

    /**
     * Enable auto-discovery.
     */
    public function enableAutoDiscovery(): static;

    /**
     * Alias for disableAutoDiscovery.
     */
    public function manualOnly(): static;

    /**
     * Check if auto-discovery is enabled.
     */
    public function isAutoDiscoveryEnabled(): bool;

    // ==================== DEPTH MANAGEMENT ====================

    /**
     * Set the maximum scanning depth.
     *
     * @param  int  $depth  The maximum depth
     */
    public function setMaxDepth(int $depth): static;

    /**
     * Get the maximum scanning depth.
     */
    public function getMaxDepth(): int;

    // ==================== RESET ====================

    /**
     * Reset all filters to default.
     */
    public function resetConfig(): static;

    // ==================== CORE DISCOVERY METHODS ====================

    /**
     * Add a custom source directory to scan for directives.
     *
     * @param  string  $directory  The directory path
     */
    public function addSource(string $directory): static;

    /**
     * Add multiple custom source directories.
     *
     * @param  array<int, string>  $directories  The directory paths
     */
    public function addSources(array $directories): static;

    /**
     * Add a directive class name directly to the collection.
     *
     * @param  class-string<AbstractDirective>  $class  The directive class name
     * @param  bool  $force  Whether to bypass reserved signature check
     *
     * @throws \InvalidArgumentException If the class does not extend AbstractDirective
     */
    public function addDirective(string $class, bool $force = false): static;

    /**
     * Add multiple directive class names directly to the collection.
     *
     * @param  array<class-string<AbstractDirective>>  $classes  Array of directive class names
     * @param  bool  $force  Whether to bypass reserved signature check
     */
    public function addDirectives(array $classes, bool $force = false): static;

    /**
     * Discovers all available directives from all sources.
     */
    public function discover(): DirectiveMetadataCollection;

    /**
     * Gets the current collection without re-discovering.
     */
    public function getCollection(): DirectiveMetadataCollection;

    /**
     * Clears the collection.
     */
    public function clear(): void;

    // ==================== RESERVED SIGNATURES ====================

    /**
     * Add a reserved signature.
     *
     * @param  string  $signature  The signature to reserve
     */
    public function addReservedSignature(string $signature): static;

    /**
     * Remove a reserved signature.
     *
     * @param  string  $signature  The signature to un-reserve
     */
    public function removeReservedSignature(string $signature): static;

    /**
     * Get all reserved signatures.
     *
     * @return array<int, string>
     */
    public function getReservedSignatures(): array;
}
