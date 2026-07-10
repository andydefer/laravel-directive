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
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\SignatureParser\SignatureParser;
use Carbon\Carbon;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionException;
use Throwable;

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
     * Whether auto-discovery is enabled.
     */
    private bool $autoDiscoveryEnabled = true;

    /**
     * Maximum directory scanning depth.
     */
    private int $maxDepth = 3;

    /**
     * @var ListCollection<MapCollection> Collection of problems encountered during discovery
     */
    private ListCollection $problems;

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
        $this->problems = new ListCollection;

        // Initialize custom sources from config
        try {
            $config = $this->getConfig();
            foreach ($config->getCustomSources() as $source) {
                $this->customSources->add($source);
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'config_loading',
                'Failed to load custom sources from configuration',
                $e->getMessage(),
                ['config_key' => 'custom_sources']
            );
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
        } catch (Throwable $e) {
            $this->addProblem(
                'parser_resolution',
                'Failed to resolve DirectiveParserInterface from container, using fallback',
                $e->getMessage(),
                ['fallback' => 'DirectiveParserService with SignatureParser']
            );

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
        } catch (Throwable $e) {
            $this->addProblem(
                'scanner_resolution',
                'Failed to resolve DirectiveScannerInterface from container, using fallback',
                $e->getMessage(),
                ['fallback' => 'DirectiveClassScanner']
            );
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
        } catch (Throwable $e) {
            $this->addProblem(
                'filesystem_resolution',
                'Failed to resolve FileSystemInterface from container, using fallback',
                $e->getMessage(),
                ['fallback' => 'FileSystemService']
            );

            return new FileSystemService;
        }
    }

    /**
     * Get the config from the container.
     */
    protected function getConfig(): DirectiveConfigInterface
    {
        try {
            return $this->container->make(DirectiveConfigInterface::class);
        } catch (Throwable $e) {
            $this->addProblem(
                'config_resolution',
                'Failed to resolve DirectiveConfigInterface from container',
                $e->getMessage(),
                []
            );
            throw $e;
        }
    }

    /**
     * Add a problem to the problems collection.
     *
     * @param  string  $key  Unique identifier for the problem
     * @param  string  $context  Human-readable description of the problem context
     * @param  string  $message  The error message
     * @param  array<string, mixed>  $contextData  Additional context data
     * @param  int  $backtraceOffset  Offset in the backtrace to find the caller
     */
    public function addProblem(
        string $key,
        string $context,
        string $message,
        array $contextData = [],
        int $backtraceOffset = 1
    ): void {
        // Récupérer la trace d'appel en ignorant les arguments pour la performance
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Trouver l'appelant réel (skip les appels internes)
        $caller = $trace[$backtraceOffset] ?? end($trace);

        $file = $caller['file'] ?? 'unknown file';
        $line = $caller['line'] ?? 0;
        $shortFile = $this->getShortFilePath($file);

        // Nettoyer le chemin du fichier pour le rendre plus lisible

        // Formatage du message avec la localisation
        $enhancedMessage = sprintf(
            '%s (in %s on line %d)',
            $message,
            $shortFile,
            $line
        );

        $this->problems = $this->problems->add(StrictAssociative::from([
            'key' => $key,
            'context' => $context,
            'message' => $enhancedMessage,
            'context_data' => $contextData,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'file' => $file,
            'line' => $line,
            'caller_function' => $caller['function'] ?? null,
            'caller_class' => $caller['class'] ?? null,
        ]));
    }

    /**
     * Get a shorter, more readable file path (truncate vendor path).
     */
    private function getShortFilePath(string $file): string
    {
        // Trouver la position de '/vendor/' dans le chemin
        $vendorPos = strpos($file, '/vendor/');

        if ($vendorPos !== false) {
            // Garder seulement le chemin à partir du dossier avant vendor
            // Ex: /home/andy-kani/pro/sites/packages/laravel-task/vendor/...
            // devient .../laravel-task/vendor/...
            $pathBeforeVendor = substr($file, 0, $vendorPos);
            $lastDir = basename($pathBeforeVendor);
            $vendorPath = substr($file, $vendorPos);

            return '.../'.$lastDir.$vendorPath;
        }

        // Si pas de vendor, garder le chemin complet
        return $file;
    }

    /**
     * Get all problems encountered during discovery.
     *
     * @return ListCollection<MapCollection> Collection of problem records
     */
    public function getProblems(): ListCollection
    {
        return $this->problems;
    }

    /**
     * Clear all problems.
     */
    public function clearProblems(): self
    {
        $this->problems = new ListCollection;

        return $this;
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
        try {
            if (! is_subclass_of($class, AbstractDirective::class)) {
                throw new \InvalidArgumentException(
                    sprintf('Class "%s" must extend %s', $class, AbstractDirective::class)
                );
            }

            if (! $this->registeredDirectives->contains($class)) {
                $this->registeredDirectives->add($class);
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'add_directive',
                'Failed to add directive class: '.$class,
                $e->getMessage(),
                ['class' => $class, 'force' => $force]
            );
            throw $e;
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
            try {
                $this->addDirective($class, $force);
            } catch (Throwable $e) {
                $this->addProblem(
                    'add_directives',
                    'Failed to add directive class: '.$class,
                    $e->getMessage(),
                    ['class' => $class, 'force' => $force]
                );
            }
        }

        return $this;
    }

    /**
     * Discovers all available directives from all sources.
     */
    public function discover(): DirectiveMetadataCollection
    {
        $this->collection = new DirectiveMetadataCollection;

        try {
            // 1. Registered directives (priority)
            $this->discoverRegisteredDirectives();
        } catch (Throwable $e) {
            $this->addProblem(
                'discover_registered',
                'Failed to discover registered directives',
                $e->getMessage(),
                []
            );
        }

        try {
            // 2. Built-in directives
            if (! $this->isSourceIgnored(DiscoverySource::BUILTIN)) {
                $this->discoverBuiltInDirectives();
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'discover_builtin',
                'Failed to discover built-in directives',
                $e->getMessage(),
                []
            );
        }

        try {
            // 3. Workspace directives
            if (! $this->isSourceIgnored(DiscoverySource::WORKSPACE)) {
                $this->discoverWorkspaceDirectives();
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'discover_workspace',
                'Failed to discover workspace directives',
                $e->getMessage(),
                []
            );
        }

        try {
            // 4. Vendor directives
            if (! $this->isSourceIgnored(DiscoverySource::VENDOR)) {
                $this->discoverVendorDirectives();
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'discover_vendor',
                'Failed to discover vendor directives',
                $e->getMessage(),
                []
            );
        }

        try {
            // 5. Custom sources
            if (! $this->isSourceIgnored(DiscoverySource::CUSTOM)) {
                $this->discoverCustomDirectives();
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'discover_custom',
                'Failed to discover custom directives',
                $e->getMessage(),
                []
            );
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
        try {
            $config = $this->getConfig();
            $reserved = $config->getReservedSignatures();
            $reserved[] = $signature;
            $config->setReservedSignatures($reserved);
        } catch (Throwable $e) {
            $this->addProblem(
                'add_reserved_signature',
                'Failed to add reserved signature: '.$signature,
                $e->getMessage(),
                ['signature' => $signature]
            );
        }

        return $this;
    }

    public function removeReservedSignature(string $signature): self
    {
        try {
            $config = $this->getConfig();
            $reserved = array_filter(
                $config->getReservedSignatures(),
                fn (string $s): bool => $s !== $signature
            );
            $config->setReservedSignatures(array_values($reserved));
        } catch (Throwable $e) {
            $this->addProblem(
                'remove_reserved_signature',
                'Failed to remove reserved signature: '.$signature,
                $e->getMessage(),
                ['signature' => $signature]
            );
        }

        return $this;
    }

    public function getReservedSignatures(): array
    {
        try {
            return $this->getConfig()->getReservedSignatures();
        } catch (Throwable $e) {
            $this->addProblem(
                'get_reserved_signatures',
                'Failed to get reserved signatures',
                $e->getMessage(),
                []
            );

            return [];
        }
    }

    // ==================== PRIVATE METHODS ====================

    private function discoverRegisteredDirectives(): void
    {
        foreach ($this->registeredDirectives as $class) {
            try {
                $this->addDirectiveFromFqcn($class, true);
            } catch (Throwable $e) {
                $this->addProblem(
                    'discover_registered_class',
                    'Failed to add registered directive: '.$class,
                    $e->getMessage(),
                    ['class' => $class]
                );
            }
        }
    }

    private function discoverBuiltInDirectives(): void
    {
        try {
            $source = new BuiltInDirectiveDiscovery;
            $fqcns = $source->discover();
            foreach ($source->getProblems() as $problem) {
                $this->addProblem(
                    'builtin_'.$problem->get('key'),
                    $problem->get('context'),
                    $problem->get('message'),
                    $problem->get('context_data')->toArray()
                );
            }

            foreach ($fqcns as $fqcn) {
                try {
                    $this->addDirectiveFromFqcn($fqcn, true);
                } catch (Throwable $e) {
                    $this->addProblem(
                        'discover_builtin_class',
                        'Failed to add built-in directive: '.$fqcn,
                        $e->getMessage(),
                        ['class' => $fqcn]
                    );
                }
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'discover_builtin_source',
                'Failed to discover built-in directives',
                $e->getMessage(),
                []
            );
        }
    }

    private function discoverWorkspaceDirectives(): void
    {
        try {
            $source = new WorkspaceDirectiveDiscovery(
                $this->getFileSystem(),
                $this->getScanner(),
                $this->getConfig(),
                $this->maxDepth
            );

            $fqcns = $source->discover();
            foreach ($source->getProblems() as $problem) {
                $this->addProblem(
                    'workspace_'.$problem->get('key'),
                    $problem->get('context'),
                    $problem->get('message'),
                    $problem->get('context_data')->toArray()
                );
            }

            foreach ($fqcns as $fqcn) {
                try {
                    $this->addDirectiveFromFqcn($fqcn, false);
                } catch (Throwable $e) {
                    $this->addProblem(
                        'discover_workspace_class',
                        'Failed to add workspace directive: '.$fqcn,
                        $e->getMessage(),
                        ['class' => $fqcn]
                    );
                }
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'discover_workspace_source',
                'Failed to discover workspace directives',
                $e->getMessage(),
                []
            );
        }
    }

    private function discoverVendorDirectives(): void
    {
        try {
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

            foreach ($source->getProblems() as $problem) {
                $this->addProblem(
                    'vendor_'.$problem->get('key'),
                    $problem->get('context'),
                    $problem->get('message'),
                    $problem->get('context_data')->toArray()
                );
            }

            foreach ($fqcns as $fqcn) {
                try {
                    $this->addDirectiveFromFqcn($fqcn, false);
                } catch (Throwable $e) {
                    $this->addProblem(
                        'discover_vendor_class',
                        'Failed to add vendor directive: '.$fqcn,
                        $e->getMessage(),
                        ['class' => $fqcn]
                    );
                }
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'discover_vendor_source',
                'Failed to discover vendor directives',
                $e->getMessage(),
                []
            );
        }
    }

    private function discoverCustomDirectives(): void
    {
        try {
            $fileSystem = $this->getFileSystem();
            $scanner = $this->getScanner();

            foreach ($this->customSources as $directory) {
                if ($this->ignoredPaths->contains($directory)) {
                    continue;
                }

                if (! $fileSystem->isDirectory($directory)) {
                    $this->addProblem(
                        'custom_source_not_directory',
                        'Custom source path is not a directory: '.$directory,
                        'Path does not exist or is not a directory',
                        ['path' => $directory]
                    );

                    continue;
                }

                try {
                    $fqcns = $scanner->scan($directory, $this->maxDepth);

                    foreach ($fqcns as $fqcn) {
                        try {
                            $this->addDirectiveFromFqcn($fqcn, false);
                        } catch (Throwable $e) {
                            $this->addProblem(
                                'discover_custom_class',
                                'Failed to add custom directive: '.$fqcn,
                                $e->getMessage(),
                                ['class' => $fqcn, 'directory' => $directory]
                            );
                        }
                    }
                } catch (Throwable $e) {
                    $this->addProblem(
                        'discover_custom_directory',
                        'Failed to scan custom directory: '.$directory,
                        $e->getMessage(),
                        ['directory' => $directory]
                    );
                }
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'discover_custom_source',
                'Failed to discover custom directives',
                $e->getMessage(),
                []
            );
        }
    }

    private function addDirectiveFromFqcn(string $fqcn, bool $force = false): void
    {
        try {
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
            if (! $force && $this->isReservedSignature($signature, $fqcn)) {
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
        } catch (ReflectionException $e) {
            $this->addProblem(
                'reflection_error',
                'Failed to reflect class: '.$fqcn,
                $e->getMessage(),
                ['class' => $fqcn]
            );
        } catch (Throwable $e) {
            $this->addProblem(
                'add_directive_fqcn',
                'Failed to add directive: '.$fqcn,
                $e->getMessage(),
                ['class' => $fqcn, 'force' => $force]
            );
        }
    }

    private function isValidDirectiveClass(ReflectionClass $reflection): bool
    {
        if ($reflection->isAbstract()) {
            return false;
        }

        return $reflection->isSubclassOf(AbstractDirective::class);
    }

    private function isReservedSignature(string $signature, string $fqcn): bool
    {
        try {
            $parsed = $this->getParser()->parse($signature, '');
            $commandName = $parsed->source;

            return in_array($commandName, $this->getConfig()->getReservedSignatures(), true);
        } catch (Throwable $e) {
            $this->addProblem(
                'reserved_signature_check',
                'Failed to check reserved signature: '.$signature." of $fqcn",
                $e->getMessage(),
                ['signature' => $signature]
            );

            return false;
        }
    }

    private function passesNamespaceFilters(string $fqcn): bool
    {
        try {
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
        } catch (Throwable $e) {
            $this->addProblem(
                'namespace_filter',
                'Failed to apply namespace filters for: '.$fqcn,
                $e->getMessage(),
                ['class' => $fqcn]
            );

            return false;
        }
    }

    private function passesPrefixFilters(string $signature): bool
    {
        try {
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
        } catch (Throwable $e) {
            $this->addProblem(
                'prefix_filter',
                'Failed to apply prefix filters for: '.$signature,
                $e->getMessage(),
                ['signature' => $signature]
            );

            return false;
        }
    }
}
