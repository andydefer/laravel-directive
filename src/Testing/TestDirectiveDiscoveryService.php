<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveHydratorService;

class TestDirectiveDiscoveryService extends DirectiveDiscoveryService
{
    /** @var array<AbstractDirective> */
    private array $registeredDirectives = [];

    public function __construct(
        DirectiveConfig $config,
        DirectiveHydratorService $hydrator,
        private readonly bool $disableFilesystemDiscovery = true,
    ) {
        parent::__construct($config, $hydrator);
    }

    public function registerDirective(AbstractDirective $directive): void
    {
        $this->registeredDirectives[] = $directive;
    }

    public function registerDirectives(array $directives): void
    {
        foreach ($directives as $directive) {
            $this->registerDirective($directive);
        }
    }

    public function registerDirectiveClass(string $className, array $constructorArgs = []): AbstractDirective
    {
        $reflection = new \ReflectionClass($className);
        $directive = $reflection->newInstanceArgs($constructorArgs);
        $this->registerDirective($directive);

        return $directive;
    }

    public function clearRegisteredDirectives(): void
    {
        $this->registeredDirectives = [];
    }

    public function getRegisteredDirectives(): array
    {
        return $this->registeredDirectives;
    }

    public function discover(): DirectiveMetadataCollection
    {
        $results = new DirectiveMetadataCollection;

        foreach ($this->registeredDirectives as $directive) {
            $metadata = new DirectiveMetadataRecord(
                signature: $directive->getSignature(),
                class: get_class($directive),
                description: $directive->getDescription(),
                aliases: $directive->getAliases(),
            );
            $results->add($metadata);
        }

        if (! $this->disableFilesystemDiscovery) {
            $results = $this->discoverFromFilesystem($results);
            $results = $this->discoverFromVendorPackagesRecursive($results);
        }

        return $results;
    }
}
