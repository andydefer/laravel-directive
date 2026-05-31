<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;

interface DirectiveLoaderInterface
{
    public function load(): DirectiveMetadataCollection;
}
