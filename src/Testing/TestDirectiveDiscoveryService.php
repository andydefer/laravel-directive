<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use ReflectionClass;

/**
 * Test-friendly version of DirectiveDiscoveryService.
 *
 * Allows registering directives programmatically without filesystem discovery.
 * Useful for unit testing where real filesystem scanning is not desired.
 */
class TestDirectiveDiscoveryService extends DirectiveDiscoveryService
{
    /** @var array<AbstractDirective> */
    private array $registeredDirectives = [];

    /**
     * @param  DirectiveConfig  $config  Configuration for directive discovery
     * @param  DirectiveHydratorService  $hydrator  Service for hydrating directives
     * @param  bool  $disableFilesystemDiscovery  If true, skip filesystem scanning
     */
    public function __construct(
        DirectiveConfig $config,
        DirectiveHydratorService $hydrator,
        private readonly bool $disableFilesystemDiscovery = true,
    ) {
        parent::__construct($config, $hydrator);
    }

    /**
     * Register a single directive instance.
     *
     * @param  AbstractDirective  $directive  The directive to register
     */
    public function registerDirective(AbstractDirective $directive): void
    {
        $this->registeredDirectives[] = $directive;
    }

    /**
     * Register multiple directive instances.
     *
     * @param  array<AbstractDirective>  $directives  Array of directives to register
     */
    public function registerDirectives(array $directives): void
    {
        foreach ($directives as $directive) {
            $this->registerDirective($directive);
        }
    }

    /**
     * Register a directive by class name.
     *
     * Instantiates the directive with given constructor arguments and registers it.
     *
     * @param  class-string<AbstractDirective>  $className  The directive class name
     * @param  array<mixed>  $constructorArgs  Constructor arguments for the directive
     * @return AbstractDirective The instantiated directive
     */
    public function registerDirectiveClass(string $className, array $constructorArgs = []): AbstractDirective
    {
        $reflection = new ReflectionClass($className);
        $directive = $reflection->newInstanceArgs($constructorArgs);
        $this->registerDirective($directive);

        return $directive;
    }

    /**
     * Clear all registered directives.
     */
    public function clearRegisteredDirectives(): void
    {
        $this->registeredDirectives = [];
    }

    /**
     * Get all registered directives.
     *
     * @return array<AbstractDirective>
     */
    public function getRegisteredDirectives(): array
    {
        return $this->registeredDirectives;
    }

    /**
     * Discover directives from registered instances and optionally from filesystem.
     *
     * @return DirectiveMetadataCollection Collection of directive metadata
     */
    public function discover(): DirectiveMetadataCollection
    {
        $results = $this->createMetadataFromRegisteredDirectives();

        if (! $this->disableFilesystemDiscovery) {
            $results = $this->discoverFromFilesystem($results);
            $results = $this->discoverFromVendorPackagesRecursive($results);
        }

        return $results;
    }

    /**
     * Create metadata collection from registered directives.
     */
    private function createMetadataFromRegisteredDirectives(): DirectiveMetadataCollection
    {
        $results = new DirectiveMetadataCollection;

        foreach ($this->registeredDirectives as $directive) {
            $metadata = $this->createMetadataFromDirective($directive);
            $results->add($metadata);
        }

        return $results;
    }

    /**
     * Create a metadata record from a directive instance.
     *
     * @param  AbstractDirective  $directive  The directive instance
     */
    private function createMetadataFromDirective(AbstractDirective $directive): DirectiveMetadataRecord
    {
        return new DirectiveMetadataRecord(
            signature: $directive->getSignature(),
            class: $directive::class,
            description: $directive->getDescription(),
            aliases: $directive->getAliases(),
        );
    }
}
