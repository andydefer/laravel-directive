<?php

// src/Sources/CompositeDiscoverySource.php

declare(strict_types=1);

namespace AndyDefer\Directive\Sources;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\DiscoverySourceInterface;

final class CompositeDiscoverySource implements DiscoverySourceInterface
{
    private array $sources = [];

    public function addSource(DiscoverySourceInterface $source): self
    {
        $this->sources[] = $source;

        return $this;
    }

    public function addSources(array $sources): self
    {
        foreach ($sources as $source) {
            $this->addSource($source);
        }

        return $this;
    }

    public function removeSource(string $name): self
    {
        $this->sources = array_filter(
            $this->sources,
            fn ($source) => $source->getName() !== $name
        );

        return $this;
    }

    public function getSources(): array
    {
        return $this->sources;
    }

    public function discover(): DirectiveMetadataCollection
    {
        $results = new DirectiveMetadataCollection;
        $signatures = [];

        foreach ($this->sources as $source) {
            $loaded = $source->discover();

            foreach ($loaded as $directive) {
                if (! in_array($directive->signature, $signatures)) {
                    $results->add($directive);
                    $signatures[] = $directive->signature;
                }
            }
        }

        return $results;
    }

    public function getName(): string
    {
        $names = array_map(fn ($source) => $source->getName(), $this->sources);

        return 'composite('.implode(', ', $names).')';
    }
}
