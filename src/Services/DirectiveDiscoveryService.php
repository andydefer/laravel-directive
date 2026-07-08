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

final class DirectiveDiscoveryService
{
    private DirectiveMetadataCollection $collection;

    private array $customSources = [];

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

    public function addSource(string $directory): self
    {
        $this->customSources[] = $directory;

        return $this;
    }

    public function addSources(array $directories): self
    {
        foreach ($directories as $directory) {
            $this->customSources[] = $directory;
        }

        return $this;
    }

    public function discover(): DirectiveMetadataCollection
    {
        // 1. Découverte des directives built-in (FORCE l'ajout)
        $builtInFqcns = $this->builtInSource->discover();
        foreach ($builtInFqcns as $fqcn) {
            $this->addDirective($fqcn, true);
        }

        // 2. Découverte dans le workspace
        $workspaceFqcns = $this->workspaceSource->discover();
        foreach ($workspaceFqcns as $fqcn) {
            $this->addDirective($fqcn, false);
        }

        // 3. Découverte dans les vendors
        $vendorFqcns = $this->vendorSource->discover();
        foreach ($vendorFqcns as $fqcn) {
            $this->addDirective($fqcn, false);
        }

        // 4. Découverte dans les sources personnalisées (config + ajouts dynamiques)
        foreach ($this->customSources as $directory) {
            if (! $this->fileSystem->isDirectory($directory)) {
                continue;
            }

            $fqcns = $this->scanner->scan($directory, $this->maxDepth);
            foreach ($fqcns as $fqcn) {
                $this->addDirective($fqcn, false);
            }
        }

        return $this->collection->uniqueByClass();
    }

    public function addReservedSignature(string $signature): self
    {
        $this->config->setReservedSignatures(
            array_merge($this->config->getReservedSignatures(), [$signature])
        );

        return $this;
    }

    public function removeReservedSignature(string $signature): self
    {
        $reserved = array_filter(
            $this->config->getReservedSignatures(),
            fn ($s) => $s !== $signature
        );

        $this->config->setReservedSignatures($reserved);

        return $this;
    }

    public function getReservedSignatures(): array
    {
        return $this->config->getReservedSignatures();
    }

    private function addDirective(string $fqcn, bool $force = false): void
    {

        $reflection = new \ReflectionClass($fqcn);

        if ($reflection->isAbstract()) {
            return;
        }

        if (! $reflection->isSubclassOf(AbstractDirective::class)) {
            return;
        }

        $instance = $reflection->newInstanceWithoutConstructor();
        $signature = $instance->getSignature();

        if (! $force && $this->isReserved($signature)) {
            return;
        }

        $this->collection->add(new DirectiveMetadataRecord(
            signature: $signature,
            class: $fqcn,
            description: $instance->getDescription(),
            aliases: $instance->getAliases(),
        ));
    }

    private function isReserved(string $signature): bool
    {
        $parsed = $this->parser->parse($signature, '');
        $commandName = $parsed->source;

        return in_array($commandName, $this->config->getReservedSignatures(), true);
    }
}
