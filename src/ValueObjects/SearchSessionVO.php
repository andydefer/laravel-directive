<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object pour l'identifiant d'une session de recherche.
 *
 * @author Andy Defer
 */
final class SearchSessionVO extends AbstractValueObject
{
    public readonly string $value;

    public function __construct(string $value = '')
    {
        $value = $value !== '' ? $value : uniqid('search_', true);

        if (strlen($value) < 5) {
            throw new InvalidArgumentException('Session ID must be at least 5 characters');
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
