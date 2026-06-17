<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object pour la limite de résultats de recherche.
 *
 * @author Andy Defer
 */
final class SearchLimitVO extends AbstractValueObject
{
    public readonly int $value;

    public function __construct(int $value = 5)
    {
        if ($value < 1) {
            throw new InvalidArgumentException("Search limit cannot be less than 1: {$value}");
        }

        if ($value > 1000) {
            throw new InvalidArgumentException("Search limit cannot exceed 1000: {$value}");
        }

        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
