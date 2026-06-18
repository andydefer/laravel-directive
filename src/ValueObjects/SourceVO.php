<?php

declare(strict_types=1);

namespace AndyDefer\Directive\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object représentant la source (le nom de la commande) d'une signature.
 */
final class SourceVO extends AbstractValueObject
{
    private string $value;

    public function __construct(string $signature, string $query)
    {
        $signatureParts = explode(' ', $signature);
        $source = $signatureParts[0] ?? '';

        if (empty($source)) {
            throw new InvalidArgumentException('Cannot extract source from signature');
        }

        $this->value = $source;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
