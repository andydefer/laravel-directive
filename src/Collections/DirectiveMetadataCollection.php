<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\DirectiveMetadataRecord;

/**
 * Collection for DirectiveMetadataRecord instances.
 *
 * @extends AbstractItemCollection<DirectiveMetadataRecord>
 */
class DirectiveMetadataCollection extends AbstractItemCollection
{
    public function __construct()
    {
        parent::__construct(DirectiveMetadataRecord::class);
    }
}
