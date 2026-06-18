<?php

declare(strict_types=1);

namespace AndyDefer\Directive\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Value Object représentant les arguments requis d'une signature.
 */
final class RequiredArgsVO extends AbstractValueObject
{
    private StringTypedCollection $values;

    public function __construct(string $signature, string $query)
    {
        $this->values = new StringTypedCollection;

        // Extraire les arguments requis de la signature
        preg_match_all('/\{([^}=?*]+)\}/', $signature, $matches);
        $requiredNames = $matches[1] ?? [];

        if (empty($requiredNames)) {
            return;
        }

        // Extraire les valeurs de la requête
        $queryParts = $this->parseQuery($query);
        $signatureParts = $this->parseSignature($signature);

        // Ne garder que les arguments qui sont requis
        foreach ($requiredNames as $index => $name) {
            $value = $queryParts[$index] ?? null;
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
        // Extraire les arguments de la requête (sans les options)
        $parts = explode(' ', $query);
        $result = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                break; // On s'arrête aux options
            }
            if (str_starts_with($part, '-')) {
                break;
            }
            if (str_starts_with($part, '[')) {
                break; // On s'arrête aux variadiques
            }
            $result[] = $part;
        }

        return $result;
    }

    private function parseSignature(string $signature): array
    {
        // Extraire tous les arguments de la signature (ordre)
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);

        return $matches[1] ?? [];
    }
}
