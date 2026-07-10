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
    public function ignoreSource(DiscoverySource|string $source): self;

    /**
     * Ignore multiple sources.
     *
     * @param  array<int, DiscoverySource|string>  $sources  The sources to ignore
     *
     * @throws \InvalidArgumentException If any source is invalid
     */
    public function ignoreSources(array $sources): self;

    /**
     * Enable a previously ignored source.
     *
     * @param  DiscoverySource|string  $source  The source to enable
     */
    public function enableSource(DiscoverySource|string $source): self;

    /**
     * Enable multiple sources.
     *
     * @param  array<int, DiscoverySource|string>  $sources  The sources to enable
     */
    public function enableSources(array $sources): self;

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
    public function ignorePath(string $path): self;

    /**
     * Ignore multiple paths.
     *
     * @param  array<int, string>  $paths  The paths to ignore
     */
    public function ignorePaths(array $paths): self;

    /**
     * Enable a previously ignored path.
     *
     * @param  string  $path  The path to enable
     */
    public function enablePath(string $path): self;

    /**
     * Enable multiple paths.
     *
     * @param  array<int, string>  $paths  The paths to enable
     */
    public function enablePaths(array $paths): self;

    // ==================== DIRECTIVE MANAGEMENT ====================

    /**
     * Ignore a directive by signature.
     *
     * @param  string  $signature  The directive signature to ignore
     */
    public function ignoreDirective(string $signature): self;

    /**
     * Ignore multiple directives by signature.
     *
     * @param  array<int, string>  $signatures  The directive signatures to ignore
     */
    public function ignoreDirectives(array $signatures): self;

    /**
     * Enable a previously ignored directive.
     *
     * @param  string  $signature  The directive signature to enable
     */
    public function enableDirective(string $signature): self;

    /**
     * Enable multiple directives.
     *
     * @param  array<int, string>  $signatures  The directive signatures to enable
     */
    public function enableDirectives(array $signatures): self;

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
    public function onlyNamespace(string $namespace): self;

    /**
     * Add multiple namespaces to the only-namespaces list.
     *
     * @param  array<int, string>  $namespaces  The namespaces to include
     */
    public function onlyNamespaces(array $namespaces): self;

    /**
     * Exclude a namespace.
     *
     * @param  string  $namespace  The namespace to exclude
     */
    public function excludeNamespace(string $namespace): self;

    /**
     * Exclude multiple namespaces.
     *
     * @param  array<int, string>  $namespaces  The namespaces to exclude
     */
    public function excludeNamespaces(array $namespaces): self;

    // ==================== PREFIX FILTERING ====================

    /**
     * Add a prefix to the only-prefixes list.
     *
     * @param  string  $prefix  The prefix to include
     */
    public function onlyPrefix(string $prefix): self;

    /**
     * Add multiple prefixes to the only-prefixes list.
     *
     * @param  array<int, string>  $prefixes  The prefixes to include
     */
    public function onlyPrefixes(array $prefixes): self;

    /**
     * Exclude a prefix.
     *
     * @param  string  $prefix  The prefix to exclude
     */
    public function excludePrefix(string $prefix): self;

    /**
     * Exclude multiple prefixes.
     *
     * @param  array<int, string>  $prefixes  The prefixes to exclude
     */
    public function excludePrefixes(array $prefixes): self;

    // ==================== AUTO-DISCOVERY ====================

    /**
     * Disable auto-discovery.
     */
    public function disableAutoDiscovery(): self;

    /**
     * Enable auto-discovery.
     */
    public function enableAutoDiscovery(): self;

    /**
     * Alias for disableAutoDiscovery.
     */
    public function manualOnly(): self;

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
    public function setMaxDepth(int $depth): self;

    /**
     * Get the maximum scanning depth.
     */
    public function getMaxDepth(): int;

    // ==================== RESET ====================

    /**
     * Reset all filters to default.
     */
    public function resetConfig(): self;

    // ==================== CORE DISCOVERY METHODS ====================

    /**
     * Add a custom source directory to scan for directives.
     *
     * @param  string  $directory  The directory path
     */
    public function addSource(string $directory): self;

    /**
     * Add multiple custom source directories.
     *
     * @param  array<int, string>  $directories  The directory paths
     */
    public function addSources(array $directories): self;

    /**
     * Add a directive class name directly to the collection.
     *
     * @param  class-string<AbstractDirective>  $class  The directive class name
     * @param  bool  $force  Whether to bypass reserved signature check
     *
     * @throws \InvalidArgumentException If the class does not extend AbstractDirective
     */
    public function addDirective(string $class, bool $force = false): self;

    /**
     * Add multiple directive class names directly to the collection.
     *
     * @param  array<class-string<AbstractDirective>>  $classes  Array of directive class names
     * @param  bool  $force  Whether to bypass reserved signature check
     */
    public function addDirectives(array $classes, bool $force = false): self;

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
    public function addReservedSignature(string $signature): self;

    /**
     * Remove a reserved signature.
     *
     * @param  string  $signature  The signature to un-reserve
     */
    public function removeReservedSignature(string $signature): self;

    /**
     * Get all reserved signatures.
     *
     * @return array<int, string>
     */
    public function getReservedSignatures(): array;
}
