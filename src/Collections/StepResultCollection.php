<?php
// src/Collections/StepResultCollection.php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\StepResultRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;

final class StepResultCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(StepResultRecord::class);
    }

    public function getByStepName(string $step_name): ?StepResultRecord
    {
        foreach ($this->items as $record) {
            if ($record->step_name === $step_name) {
                return $record;
            }
        }

        return null;
    }

    public function getOrderedByExecution(): self
    {
        $items = $this->items;
        usort($items, fn($a, $b) => $a->executed_at <=> $b->executed_at);

        $collection = new self();
        foreach ($items as $item) {
            $collection->add($item);
        }

        return $collection;
    }
}
