<?php

// src/Collections/ParsedOptionCollection.php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\ParsedOptionRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;

final class ParsedOptionCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ParsedOptionRecord::class);
    }

    public function get(string $name): ?string
    {
        foreach ($this->items as $record) {
            if ($record->name === $name && ! $record->is_flag) {
                return $record->value;
            }
        }

        return null;
    }

    public function getValue(string $name): ?string
    {
        return $this->get($name);
    }

    public function isFlag(string $name): bool
    {
        foreach ($this->items as $record) {
            if ($record->name === $name) {
                return $record->is_flag;
            }
        }

        return false;
    }

    public function has(string $name): bool
    {
        foreach ($this->items as $record) {
            if ($record->name === $name) {
                return true;
            }
        }

        return false;
    }

    public function isTrue(string $name): bool
    {
        foreach ($this->items as $record) {
            if ($record->name === $name) {
                if ($record->is_flag) {
                    return true;
                }

                return $record->value === 'true' || $record->value === '1';
            }
        }

        return false;
    }

    public function toAssociativeArray(): array
    {
        $result = [];
        foreach ($this->items as $record) {
            if ($record->is_flag) {
                $result[$record->name] = true;
            } else {
                $result[$record->name] = $record->value;
            }
        }

        return $result;
    }

    public function addOption(string $name, string $value, bool $isFlag): void
    {
        $this->add(new ParsedOptionRecord($name, $value, $isFlag));
    }
}
