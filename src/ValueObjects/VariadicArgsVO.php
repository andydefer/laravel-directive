<?php

declare(strict_types=1);

namespace AndyDefer\Directive\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Value Object représentant les arguments variadiques d'une signature.
 */
final class VariadicArgsVO extends AbstractValueObject
{
    private StringTypedCollection $values;

    public function __construct(string $signature, string $query)
    {
        $this->values = new StringTypedCollection;

        // Vérifier si la signature a un argument variadique
        if (! str_contains($signature, '{*}')) {
            return;
        }

        // Extraire les valeurs variadiques de la requête
        $values = $this->parseQuery($query);

        foreach ($values as $value) {
            $this->values->add($value);
        }
    }

    public function getValue(): StringTypedCollection
    {
        return $this->values;
    }

    public function toArray(): array
    {
        return $this->values->toArray();
    }

    private function parseQuery(string $query): array
    {
        // Chercher les crochets [...] pour les variadiques
        $parts = explode(' ', $query);
        $result = [];
        $inVariadic = false;

        foreach ($parts as $part) {
            if (str_starts_with($part, '[')) {
                $inVariadic = true;
                // Enlever le '[' du début
                $part = substr($part, 1);
            }

            if ($inVariadic) {
                // Enlever le ']' de la fin
                $part = rtrim($part, ']');
                if (! empty($part)) {
                    $result[] = $part;
                }
            }

            if (str_ends_with($part, ']')) {
                $inVariadic = false;
            }
        }

        return $result;
    }
}
