<?php
// src/Collections/ExtractedParameterCollection.php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\ExtractedParameterRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;

final class ExtractedParameterCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ExtractedParameterRecord::class);
    }

    public function getNonOptions(): self
    {
        return $this->filter(fn(ExtractedParameterRecord $record) => !$record->isOption);
    }

    public function getOptions(): self
    {
        return $this->filter(fn(ExtractedParameterRecord $record) => $record->isOption);
    }

    public function getRequired(): self
    {
        return $this->filter(fn(ExtractedParameterRecord $record) => $record->required);
    }

    public function getVariadic(): ?ExtractedParameterRecord
    {
        foreach ($this->items as $record) {
            if ($record->isVariadic) {
                return $record;
            }
        }
        return null;
    }

    public function hasVariadic(): bool
    {
        return $this->getVariadic() !== null;
    }
}
