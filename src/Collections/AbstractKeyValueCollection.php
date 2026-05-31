<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Records\ReplacementRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;

/**
 * Abstract base class for collections that store key-value pair records.
 *
 * Provides common methods for collections of ParameterRecord or ReplacementRecord.
 *
 * @template TRecord of ParameterRecord|ReplacementRecord
 * @extends AbstractTypedCollection<TRecord>
 */
abstract class AbstractKeyValueCollection extends AbstractItemCollection
{
    /**
     * Convert collection to associative array.
     *
     * @return array<string, bool|string|int|null>
     */
    abstract public function toAssociativeArray(): array;
}
