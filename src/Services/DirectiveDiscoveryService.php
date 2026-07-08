<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\DiscoverySourceInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Contracts\Services\DirectiveParserInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use ReflectionClass;

/**
 * Discovers and manages directive classes from multiple sources.
 *
 * This service orchestrates the discovery of directives from built-in sources,
 * the workspace, vendor packages, and custom sources. It handles deduplication,
 * reserved signature filtering, and provides a unified collection of all
 * available directives.
 */
final class DirectiveDiscoveryService
{
    /**
     * The collection of discovered directive metadata.
     */
    private DirectiveMetadataCollection $collection;

    /**
     * Custom source directories to scan for directives.
     *
     * @var array<int, string>
     */
    private array $customSources = [];

    /**
     * @param  DiscoverySourceInterface  $builtInSource  The built-in directives source
     * @param  DiscoverySourceInterface  $workspaceSource  The workspace directives source
     * @param  DiscoverySourceInterface  $vendorSource  The vendor directives source
     * @param  DirectiveParserInterface  $parser  The directive parser
     * @param  DirectiveScannerInterface  $scanner  The directive scanner
     * @param  FileSystemInterface  $fileSystem  The filesystem service
     * @param  DirectiveConfigInterface  $config  The directive configuration
     * @param  int  $maxDepth  Maximum directory scanning depth
     */
    public function __construct(
        private readonly DiscoverySourceInterface $builtInSource,
        private readonly DiscoverySourceInterface $workspaceSource,
        private readonly DiscoverySourceInterface $vendorSource,
        private readonly DirectiveParserInterface $parser,
        private readonly DirectiveScannerInterface $scanner,
        private readonly FileSystemInterface $fileSystem,
        private readonly DirectiveConfigInterface $config,
        private readonly int $maxDepth = 3,
    ) {
        $this->collection = new DirectiveMetadataCollection;
        $this->customSources = $this->config->getCustomSources();
    }

    /**
     * Adds a custom source directory to scan for directives.
     *
     * @param  string  $directory  The directory path
     */
    public function addSource(string $directory): self
    {
        $this->customSources[] = $directory;

        return $this;
    }

    /**
     * Adds multiple custom source directories to scan for directives.
     *
     * @param  array<int, string>  $directories  The directory paths
     */
    public function addSources(array $directories): self
    {
        foreach ($directories as $directory) {
            $this->customSources[] = $directory;
        }

        return $this;
    }

    /**
     * Discovers all available directives from all sources.
     *
     * @return DirectiveMetadataCollection The collection of discovered directives
     */
    public function discover(): DirectiveMetadataCollection
    {
        $this->discoverBuiltInDirectives();
        $this->discoverWorkspaceDirectives();
        $this->discoverVendorDirectives();
        $this->discoverCustomDirectives();

        return $this->collection->uniqueByClass();
    }

    /**
     * Adds a signature to the reserved list.
     *
     * @param  string  $signature  The signature to reserve
     */
    public function addReservedSignature(string $signature): self
    {
        $reserved = $this->config->getReservedSignatures();
        $reserved[] = $signature;

        $this->config->setReservedSignatures($reserved);

        return $this;
    }

    /**
     * Removes a signature from the reserved list.
     *
     * @param  string  $signature  The signature to remove
     */
    public function removeReservedSignature(string $signature): self
    {
        $reserved = array_filter(
            $this->config->getReservedSignatures(),
            fn (string $s): bool => $s !== $signature
        );

        $this->config->setReservedSignatures(array_values($reserved));

        return $this;
    }

    /**
     * Gets the list of reserved signatures.
     *
     * @return array<int, string> The reserved signatures
     */
    public function getReservedSignatures(): array
    {
        return $this->config->getReservedSignatures();
    }

    /**
     * Discovers built-in directives.
     */
    private function discoverBuiltInDirectives(): void
    {
        $fqcns = $this->builtInSource->discover();

        foreach ($fqcns as $fqcn) {
            $this->addDirective($fqcn, true);
        }
    }

    /**
     * Discovers workspace directives.
     */
    private function discoverWorkspaceDirectives(): void
    {
        $fqcns = $this->workspaceSource->discover();

        foreach ($fqcns as $fqcn) {
            $this->addDirective($fqcn, false);
        }
    }

    /**
     * Discovers vendor package directives.
     */
    private function discoverVendorDirectives(): void
    {
        $fqcns = $this->vendorSource->discover();

        foreach ($fqcns as $fqcn) {
            $this->addDirective($fqcn, false);
        }
    }

    /**
     * Discovers custom source directives.
     */
    private function discoverCustomDirectives(): void
    {
        foreach ($this->customSources as $directory) {
            if (! $this->fileSystem->isDirectory($directory)) {
                continue;
            }

            $fqcns = $this->scanner->scan($directory, $this->maxDepth);

            foreach ($fqcns as $fqcn) {
                $this->addDirective($fqcn, false);
            }
        }
    }

    /**
     * Adds a directive to the collection if it is valid.
     *
     * @param  string  $fqcn  The fully qualified class name
     * @param  bool  $force  Whether to bypass reserved signature check
     */
    private function addDirective(string $fqcn, bool $force = false): void
    {
        $reflection = new ReflectionClass($fqcn);

        if (! $this->isValidDirectiveClass($reflection)) {
            return;
        }

        $instance = $reflection->newInstanceWithoutConstructor();
        $signature = $instance->getSignature();

        if (! $force && $this->isReservedSignature($signature)) {
            return;
        }

        $this->collection->add(new DirectiveMetadataRecord(
            signature: $signature,
            class: $fqcn,
            description: $instance->getDescription(),
            aliases: $instance->getAliases(),
        ));
    }

    /**
     * Checks if a class is a valid directive class.
     *
     * @param  ReflectionClass  $reflection  The class reflection
     * @return bool True if the class is a valid directive
     */
    private function isValidDirectiveClass(ReflectionClass $reflection): bool
    {
        if ($reflection->isAbstract()) {
            return false;
        }

        return $reflection->isSubclassOf(AbstractDirective::class);
    }

    /**
     * Checks if a signature is reserved.
     *
     * @param  string  $signature  The signature to check
     * @return bool True if the signature is reserved
     */
    private function isReservedSignature(string $signature): bool
    {
        $parsed = $this->parser->parse($signature, '');
        $commandName = $parsed->source;

        return in_array($commandName, $this->config->getReservedSignatures(), true);
    }
}
