<?php

// src/Collections/ParsedArgumentCollection.php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\ParsedArgumentRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;

final class ParsedArgumentCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ParsedArgumentRecord::class);
    }

    public function get(string $name): ?string
    {
        foreach ($this->items as $record) {
            if ($record->name === $name) {
                return $record->value;
            }
        }

        return null;
    }

    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    public function toAssociativeArray(): array
    {
        $result = [];
        foreach ($this->items as $record) {
            $result[$record->name] = $record->value;
        }

        return $result;
    }

    public function addArgument(string $name, string $value): void
    {
        $this->add(new ParsedArgumentRecord($name, $value));
    }
}
