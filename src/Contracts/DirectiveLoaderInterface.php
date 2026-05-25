<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Records\Collections\TypedCollection;

interface DirectiveLoaderInterface
{
    public function load(): TypedCollection;
}
