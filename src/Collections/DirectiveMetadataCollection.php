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

    /**
     * Remove duplicate directives based on signature.
     *
     * @return self New collection with unique directives
     */
    public function unique(): self
    {
        $seen = [];
        $result = new self;

        foreach ($this->items as $item) {
            $key = $item->signature;

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $result->add($item);
            }
        }

        return $result;
    }

    /**
     * Remove duplicate directives based on class name.
     *
     * @return self New collection with unique directives by class
     */
    public function uniqueByClass(): self
    {
        $seen = [];
        $result = new self;

        foreach ($this->items as $item) {
            $key = $item->class;

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $result->add($item);
            }
        }

        return $result;
    }
}
