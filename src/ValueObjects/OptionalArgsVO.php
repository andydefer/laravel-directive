<?php

declare(strict_types=1);

namespace AndyDefer\Directive\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Value Object représentant les arguments optionnels d'une signature.
 */
final class OptionalArgsVO extends AbstractValueObject
{
    private StringTypedCollection $values;

    public function __construct(string $signature, string $query)
    {
        $this->values = new StringTypedCollection;

        // Extraire les arguments optionnels de la signature
        preg_match_all('/\{([^=}?*]+)\?}/', $signature, $matches);
        $optionalNames = $matches[1] ?? [];

        if (empty($optionalNames)) {
            return;
        }

        // Extraire les valeurs de la requête
        $queryParts = $this->parseQuery($query);
        $signatureParts = $this->parseSignature($signature);

        // Pour chaque argument optionnel, trouver sa valeur
        foreach ($optionalNames as $index => $name) {
            // Trouver l'index de l'argument optionnel dans la signature
            $position = array_search($name.'?', $signatureParts);
            if ($position === false) {
                continue;
            }

            $value = $queryParts[$position] ?? null;
            if ($value !== null) {
                $this->values->add($value);
            }
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
        $parts = explode(' ', $query);
        $result = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                break;
            }
            if (str_starts_with($part, '-')) {
                break;
            }
            if (str_starts_with($part, '[')) {
                break;
            }
            $result[] = $part;
        }

        return $result;
    }

    private function parseSignature(string $signature): array
    {
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);

        return $matches[1] ?? [];
    }
}
