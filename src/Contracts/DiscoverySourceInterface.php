<?php

// src/Contracts/DiscoverySourceInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;

interface DiscoverySourceInterface
{
    public function discover(): DirectiveMetadataCollection;

    public function getName(): string;
}
