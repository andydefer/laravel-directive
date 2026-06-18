<?php

// src/Services/DirectiveDiscoveryService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\DiscoverySourceInterface;
use AndyDefer\Directive\Sources\CompositeDiscoverySource;

class DirectiveDiscoveryService
{
    private CompositeDiscoverySource $source;

    public function __construct(
        CompositeDiscoverySource $source,
    ) {
        $this->source = $source;
    }

    public function discover(): DirectiveMetadataCollection
    {
        return $this->source->discover();
    }

    public function addSource(DiscoverySourceInterface $source): self
    {
        $this->source->addSource($source);

        return $this;
    }

    public function getSources(): array
    {
        return $this->source->getSources();
    }
}
