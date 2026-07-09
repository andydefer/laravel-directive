<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Container\Container;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Contracts\Services\DirectiveDiscoveryInterface;
use AndyDefer\Directive\Contracts\Services\DirectiveParserInterface;
use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Enums\DiscoverySource;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\SignatureParser\SignatureParser;
use PhpParser\ParserFactory;
use ReflectionClass;

/**
 * Discovers and manages directive classes from multiple sources.
 */
class DirectiveDiscoveryService implements DirectiveDiscoveryInterface
{
    private const MIN_DEPTH = 2;

    private const MAX_DEPTH = 7;

    /**
     * The collection of discovered directive metadata.
     */
    private DirectiveMetadataCollection $collection;

    /**
     * Custom source directories to scan for directives.
     */
    private StringTypedCollection $customSources;

    /**
     * Registered directive class names.
     */
    private StringTypedCollection $registeredDirectives;

    /**
     * Ignored sources.
     */
    private StringTypedCollection $ignoredSources;

    /**
     * Ignored paths.
     */
    private StringTypedCollection $ignoredPaths;

    /**
     * Ignored directive signatures.
     */
    private StringTypedCollection $ignoredDirectives;

    /**
     * Only namespaces to include.
     */
    private StringTypedCollection $onlyNamespaces;

    /**
     * Excluded namespaces.
     */
    private StringTypedCollection $excludedNamespaces;

    /**
     * Only prefixes to include.
     */
    private StringTypedCollection $onlyPrefixes;

    /**
     * Excluded prefixes.
     */
    private StringTypedCollection $excludedPrefixes;

    /**
     * Whether silent mode is enabled.
     */
    private bool $silent = false;

    /**
     * Whether auto-discovery is enabled.
     */
    private bool $autoDiscoveryEnabled = true;

    /**
     * Maximum directory scanning depth.
     */
    private int $maxDepth = 3;

    /**
     * @param  Container  $container  The container instance
     */
    protected function __construct(
        private readonly Container $container,
    ) {
        $this->collection = new DirectiveMetadataCollection;
        $this->customSources = new StringTypedCollection;
        $this->registeredDirectives = new StringTypedCollection;
        $this->ignoredSources = new StringTypedCollection;
        $this->ignoredPaths = new StringTypedCollection;
        $this->ignoredDirectives = new StringTypedCollection;
        $this->onlyNamespaces = new StringTypedCollection;
        $this->excludedNamespaces = new StringTypedCollection;
        $this->onlyPrefixes = new StringTypedCollection;
        $this->excludedPrefixes = new StringTypedCollection;
        $this->maxDepth = 3;

        // Initialize custom sources from config
        $config = $this->getConfig();
        foreach ($config->getCustomSources() as $source) {
            $this->customSources->add($source);
        }
    }

    /**
     * Initialize the discovery service with a container.
     */
    public static function init(Container $container): self
    {
        return new self($container);
    }

    /**
     * Set the maximum scanning depth.
     * Clamped between MIN_DEPTH and MAX_DEPTH.
     */
    public function setMaxDepth(int $depth): self
    {
        $this->maxDepth = max(self::MIN_DEPTH, min(self::MAX_DEPTH, $depth));

        return $this;
    }

    /**
     * Get the maximum scanning depth.
     */
    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    /**
     * Get the parser from the container.
     */
    private function getParser(): DirectiveParserInterface
    {
        try {
            return $this->container->make(DirectiveParserInterface::class);
        } catch (\Throwable $e) {
            return new DirectiveParserService(new SignatureParser);
        }
    }

    /**
     * Get the scanner from the container.
     */
    private function getScanner(): DirectiveScannerInterface
    {
        try {
            return $this->container->make(DirectiveScannerInterface::class);
        } catch (\Throwable $e) {
            $fileSystem = $this->getFileSystem();
            $parser = (new ParserFactory)->createForNewestSupportedVersion();

            return new DirectiveClassScanner($fileSystem, $parser);
        }
    }

    /**
     * Get the filesystem from the container.
     */
    private function getFileSystem(): FileSystemInterface
    {
        try {
            return $this->container->make(FileSystemInterface::class);
        } catch (\Throwable $e) {
            return new FileSystemService;
        }
    }

    /**
     * Get the config from the container.
     */
    protected function getConfig(): DirectiveConfigInterface
    {
        return $this->container->make(DirectiveConfigInterface::class);
    }
    // ==================== SOURCE MANAGEMENT ====================

    /**
     * Ignore a source.
     *
     * @param  DiscoverySource|string  $source  The source to ignore
     */
    public function ignoreSource(DiscoverySource|string $source): self
    {
        $value = $source instanceof DiscoverySource ? $source->value : $source;

        if (! $this->ignoredSources->contains($value)) {
            $this->ignoredSources->add($value);
        }

        return $this;
    }

    /**
     * Ignore multiple sources.
     *
     * @param  array<DiscoverySource|string>  $sources  The sources to ignore
     */
    public function ignoreSources(array $sources): self
    {
        foreach ($sources as $source) {
            $this->ignoreSource($source);
        }

        return $this;
    }

    /**
     * Enable a previously ignored source.
     *
     * @param  DiscoverySource|string  $source  The source to enable
     */
    public function enableSource(DiscoverySource|string $source): self
    {
        $value = $source instanceof DiscoverySource ? $source->value : $source;

        $this->ignoredSources = $this->ignoredSources->filter(
            fn (string $s): bool => $s !== $value
        );

        return $this;
    }

    /**
     * Enable multiple sources.
     *
     * @param  array<DiscoverySource|string>  $sources  The sources to enable
     */
    public function enableSources(array $sources): self
    {
        foreach ($sources as $source) {
            $this->enableSource($source);
        }

        return $this;
    }

    /**
     * Check if a source is ignored.
     *
     * @param  DiscoverySource|string  $source  The source to check
     */
    public function isSourceIgnored(DiscoverySource|string $source): bool
    {
        $value = $source instanceof DiscoverySource ? $source->value : $source;

        return $this->ignoredSources->contains($value);
    }

    // ==================== PATH MANAGEMENT ====================

    /**
     * Ignore a path.
     */
    public function ignorePath(string $path): self
    {
        if (! $this->ignoredPaths->contains($path)) {
            $this->ignoredPaths->add($path);
        }

        return $this;
    }

    /**
     * Ignore multiple paths.
     */
    public function ignorePaths(array $paths): self
    {
        foreach ($paths as $path) {
            $this->ignorePath($path);
        }

        return $this;
    }

    /**
     * Enable a previously ignored path.
     */
    public function enablePath(string $path): self
    {
        $this->ignoredPaths = $this->ignoredPaths->filter(
            fn (string $p): bool => $p !== $path
        );

        return $this;
    }

    /**
     * Enable multiple paths.
     */
    public function enablePaths(array $paths): self
    {
        foreach ($paths as $path) {
            $this->enablePath($path);
        }

        return $this;
    }

    // ==================== DIRECTIVE MANAGEMENT ====================

    /**
     * Ignore a directive by signature.
     */
    public function ignoreDirective(string $signature): self
    {
        $parts = explode(' ', $signature);
        $baseSignature = $parts[0];

        if (! $this->ignoredDirectives->contains($baseSignature)) {
            $this->ignoredDirectives->add($baseSignature);
        }

        return $this;
    }

    /**
     * Ignore multiple directives by signature.
     */
    public function ignoreDirectives(array $signatures): self
    {
        foreach ($signatures as $signature) {
            $this->ignoreDirective($signature);
        }

        return $this;
    }

    /**
     * Enable a previously ignored directive.
     */
    public function enableDirective(string $signature): self
    {
        $parts = explode(' ', $signature);
        $baseSignature = $parts[0];

        $this->ignoredDirectives = $this->ignoredDirectives->filter(
            fn (string $s): bool => $s !== $baseSignature
        );

        return $this;
    }

    /**
     * Enable multiple directives.
     */
    public function enableDirectives(array $signatures): self
    {
        foreach ($signatures as $signature) {
            $this->enableDirective($signature);
        }

        return $this;
    }

    /**
     * Check if a directive is ignored.
     */
    public function isDirectiveIgnored(string $signature): bool
    {
        $parts = explode(' ', $signature);
        $baseSignature = $parts[0];

        return $this->ignoredDirectives->contains($baseSignature);
    }

    // ==================== NAMESPACE FILTERING ====================

    /**
     * Add a namespace to the only-namespaces list.
     */
    public function onlyNamespace(string $namespace): self
    {
        if (! $this->onlyNamespaces->contains($namespace)) {
            $this->onlyNamespaces->add($namespace);
        }

        return $this;
    }

    /**
     * Add multiple namespaces to the only-namespaces list.
     */
    public function onlyNamespaces(array $namespaces): self
    {
        foreach ($namespaces as $namespace) {
            $this->onlyNamespace($namespace);
        }

        return $this;
    }

    /**
     * Exclude a namespace.
     */
    public function excludeNamespace(string $namespace): self
    {
        if (! $this->excludedNamespaces->contains($namespace)) {
            $this->excludedNamespaces->add($namespace);
        }

        return $this;
    }

    /**
     * Exclude multiple namespaces.
     */
    public function excludeNamespaces(array $namespaces): self
    {
        foreach ($namespaces as $namespace) {
            $this->excludeNamespace($namespace);
        }

        return $this;
    }

    // ==================== PREFIX FILTERING ====================

    /**
     * Add a prefix to the only-prefixes list.
     */
    public function onlyPrefix(string $prefix): self
    {
        if (! $this->onlyPrefixes->contains($prefix)) {
            $this->onlyPrefixes->add($prefix);
        }

        return $this;
    }

    /**
     * Add multiple prefixes to the only-prefixes list.
     */
    public function onlyPrefixes(array $prefixes): self
    {
        foreach ($prefixes as $prefix) {
            $this->onlyPrefix($prefix);
        }

        return $this;
    }

    /**
     * Exclude a prefix.
     */
    public function excludePrefix(string $prefix): self
    {
        if (! $this->excludedPrefixes->contains($prefix)) {
            $this->excludedPrefixes->add($prefix);
        }

        return $this;
    }

    /**
     * Exclude multiple prefixes.
     */
    public function excludePrefixes(array $prefixes): self
    {
        foreach ($prefixes as $prefix) {
            $this->excludePrefix($prefix);
        }

        return $this;
    }

    // ==================== SILENT MODE ====================

    /**
     * Enable or disable silent mode.
     */
    public function silent(bool $enabled = true): self
    {
        $this->silent = $enabled;

        return $this;
    }

    /**
     * Enable output (disable silent mode).
     */
    public function withOutput(): self
    {
        $this->silent = false;

        return $this;
    }

    /**
     * Disable output (enable silent mode).
     */
    public function withoutOutput(): self
    {
        $this->silent = true;

        return $this;
    }

    /**
     * Check if silent mode is enabled.
     */
    public function isSilent(): bool
    {
        return $this->silent;
    }

    // ==================== AUTO-DISCOVERY ====================

    /**
     * Disable auto-discovery.
     */
    public function disableAutoDiscovery(): self
    {
        $this->autoDiscoveryEnabled = false;

        return $this;
    }

    /**
     * Enable auto-discovery.
     */
    public function enableAutoDiscovery(): self
    {
        $this->autoDiscoveryEnabled = true;

        return $this;
    }

    /**
     * Alias for disableAutoDiscovery.
     */
    public function manualOnly(): self
    {
        return $this->disableAutoDiscovery();
    }

    /**
     * Check if auto-discovery is enabled.
     */
    public function isAutoDiscoveryEnabled(): bool
    {
        return $this->autoDiscoveryEnabled;
    }

    // ==================== RESET ====================

    /**
     * Reset all filters to default.
     */
    public function resetConfig(): self
    {
        $this->ignoredSources = new StringTypedCollection;
        $this->ignoredPaths = new StringTypedCollection;
        $this->ignoredDirectives = new StringTypedCollection;
        $this->onlyNamespaces = new StringTypedCollection;
        $this->excludedNamespaces = new StringTypedCollection;
        $this->onlyPrefixes = new StringTypedCollection;
        $this->excludedPrefixes = new StringTypedCollection;
        $this->silent = false;
        $this->autoDiscoveryEnabled = true;
        $this->maxDepth = 3;

        return $this;
    }

    // ==================== CORE DISCOVERY METHODS ====================

    /**
     * Add a custom source directory to scan for directives.
     */
    public function addSource(string $directory): self
    {
        if (! $this->customSources->contains($directory)) {
            $this->customSources->add($directory);
        }

        return $this;
    }

    /**
     * Add multiple custom source directories.
     */
    public function addSources(array $directories): self
    {
        foreach ($directories as $directory) {
            $this->addSource($directory);
        }

        return $this;
    }

    /**
     * Add a directive class name directly to the collection.
     *
     * @param  class-string<AbstractDirective>  $class  The directive class name
     * @param  bool  $force  Whether to bypass reserved signature check
     *
     * @throws \InvalidArgumentException If the class does not extend AbstractDirective
     */
    public function addDirective(string $class, bool $force = false): self
    {
        if (! is_subclass_of($class, AbstractDirective::class)) {
            throw new \InvalidArgumentException(
                sprintf('Class "%s" must extend %s', $class, AbstractDirective::class)
            );
        }

        if (! $this->registeredDirectives->contains($class)) {
            $this->registeredDirectives->add($class);
        }

        return $this;
    }

    /**
     * Add multiple directive class names directly to the collection.
     *
     * @param  array<class-string<AbstractDirective>>  $classes  Array of directive class names
     * @param  bool  $force  Whether to bypass reserved signature check
     */
    public function addDirectives(array $classes, bool $force = false): self
    {
        foreach ($classes as $class) {
            $this->addDirective($class, $force);
        }

        return $this;
    }

    /**
     * Discovers all available directives from all sources.
     */
    public function discover(): DirectiveMetadataCollection
    {
        $this->collection = new DirectiveMetadataCollection;

        // 1. Registered directives (priority)
        $this->discoverRegisteredDirectives();

        // 2. Built-in directives
        if (! $this->isSourceIgnored(DiscoverySource::BUILTIN)) {
            $this->discoverBuiltInDirectives();
        }

        // 3. Workspace directives
        if (! $this->isSourceIgnored(DiscoverySource::WORKSPACE)) {
            $this->discoverWorkspaceDirectives();
        }

        // 4. Vendor directives
        if (! $this->isSourceIgnored(DiscoverySource::VENDOR)) {
            $this->discoverVendorDirectives();
        }

        // 5. Custom sources
        if (! $this->isSourceIgnored(DiscoverySource::CUSTOM)) {
            $this->discoverCustomDirectives();
        }

        return $this->collection->uniqueByClass();
    }

    /**
     * Gets the current collection without re-discovering.
     */
    public function getCollection(): DirectiveMetadataCollection
    {
        return $this->collection;
    }

    /**
     * Clears the collection.
     */
    public function clear(): void
    {
        $this->collection = new DirectiveMetadataCollection;
    }

    // ==================== RESERVED SIGNATURES ====================

    public function addReservedSignature(string $signature): self
    {
        $config = $this->getConfig();
        $reserved = $config->getReservedSignatures();
        $reserved[] = $signature;
        $config->setReservedSignatures($reserved);

        return $this;
    }

    public function removeReservedSignature(string $signature): self
    {
        $config = $this->getConfig();
        $reserved = array_filter(
            $config->getReservedSignatures(),
            fn (string $s): bool => $s !== $signature
        );
        $config->setReservedSignatures(array_values($reserved));

        return $this;
    }

    public function getReservedSignatures(): array
    {
        return $this->getConfig()->getReservedSignatures();
    }

    // ==================== PRIVATE METHODS ====================

    private function discoverRegisteredDirectives(): void
    {
        foreach ($this->registeredDirectives as $class) {
            $this->addDirectiveFromFqcn($class, true);
        }
    }

    private function discoverBuiltInDirectives(): void
    {
        $source = new BuiltInDirectiveDiscovery;
        $fqcns = $source->discover();

        foreach ($fqcns as $fqcn) {
            $this->addDirectiveFromFqcn($fqcn, true);
        }
    }

    private function discoverWorkspaceDirectives(): void
    {
        $source = new WorkspaceDirectiveDiscovery(
            $this->getFileSystem(),
            $this->getScanner(),
            $this->getConfig(),
            $this->maxDepth
        );

        $fqcns = $source->discover();

        foreach ($fqcns as $fqcn) {
            $this->addDirectiveFromFqcn($fqcn, false);
        }
    }

    private function discoverVendorDirectives(): void
    {
        $config = $this->getConfig();
        $fileSystem = $this->getFileSystem();

        $composerReader = new ComposerReaderService($config, $fileSystem);
        $dependencyResolver = new DependencyResolverService($composerReader, $fileSystem);

        $source = new VendorDirectiveDiscovery(
            $composerReader,
            $dependencyResolver,
            $fileSystem,
            $this->getScanner(),
            $this->maxDepth
        );

        $fqcns = $source->discover();

        foreach ($fqcns as $fqcn) {
            $this->addDirectiveFromFqcn($fqcn, false);
        }
    }

    private function discoverCustomDirectives(): void
    {
        $fileSystem = $this->getFileSystem();
        $scanner = $this->getScanner();

        foreach ($this->customSources as $directory) {
            if ($this->ignoredPaths->contains($directory)) {
                continue;
            }

            if (! $fileSystem->isDirectory($directory)) {
                continue;
            }

            $fqcns = $scanner->scan($directory, $this->maxDepth);

            foreach ($fqcns as $fqcn) {
                $this->addDirectiveFromFqcn($fqcn, false);
            }
        }
    }

    private function addDirectiveFromFqcn(string $fqcn, bool $force = false): void
    {
        $reflection = new ReflectionClass($fqcn);

        if (! $this->isValidDirectiveClass($reflection)) {
            return;
        }

        $instance = $reflection->newInstanceWithoutConstructor();
        $signature = $instance->getSignature();

        $parts = explode(' ', $signature);
        $baseSignature = $parts[0];

        // Check if directive is ignored
        if ($this->ignoredDirectives->contains($baseSignature)) {
            return;
        }

        // Check reserved signatures
        if (! $force && $this->isReservedSignature($signature)) {
            return;
        }

        // Check namespace filters
        if (! $this->passesNamespaceFilters($fqcn)) {
            return;
        }

        // Check prefix filters
        if (! $this->passesPrefixFilters($signature)) {
            return;
        }

        $this->collection->add(new DirectiveMetadataRecord(
            signature: $signature,
            class: $fqcn,
            description: $instance->getDescription(),
            aliases: $instance->getAliases(),
        ));
    }

    private function isValidDirectiveClass(ReflectionClass $reflection): bool
    {
        if ($reflection->isAbstract()) {
            return false;
        }

        return $reflection->isSubclassOf(AbstractDirective::class);
    }

    private function isReservedSignature(string $signature): bool
    {
        $parsed = $this->getParser()->parse($signature, '');
        $commandName = $parsed->source;

        return in_array($commandName, $this->getConfig()->getReservedSignatures(), true);
    }

    private function passesNamespaceFilters(string $fqcn): bool
    {
        // If only namespaces are defined, the class must be in one of them
        if ($this->onlyNamespaces->isNotEmpty()) {
            $passed = false;
            foreach ($this->onlyNamespaces as $namespace) {
                if (str_starts_with($fqcn, $namespace)) {
                    $passed = true;
                    break;
                }
            }
            if (! $passed) {
                return false;
            }
        }

        // Check excluded namespaces
        foreach ($this->excludedNamespaces as $namespace) {
            if (str_starts_with($fqcn, $namespace)) {
                return false;
            }
        }

        return true;
    }

    private function passesPrefixFilters(string $signature): bool
    {
        // If only prefixes are defined, the signature must start with one of them
        if ($this->onlyPrefixes->isNotEmpty()) {
            $passed = false;
            foreach ($this->onlyPrefixes as $prefix) {
                if (str_starts_with($signature, $prefix)) {
                    $passed = true;
                    break;
                }
            }
            if (! $passed) {
                return false;
            }
        }

        // Check excluded prefixes
        foreach ($this->excludedPrefixes as $prefix) {
            if (str_starts_with($signature, $prefix)) {
                return false;
            }
        }

        return true;
    }
}
