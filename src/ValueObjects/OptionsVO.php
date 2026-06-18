<?php

declare(strict_types=1);

namespace AndyDefer\Directive\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

/**
 * Value Object représentant les options d'une signature.
 */
final class OptionsVO extends AbstractValueObject
{
    private array $values = [];

    public function __construct(string $signature, string $query)
    {
        // Extraire les options de la signature
        preg_match_all('/\{--([^}=]+)\}/', $signature, $matches);
        $optionNames = $matches[1] ?? [];

        // Extraire les options de la requête
        $queryOptions = $this->parseOptions($query);

        foreach ($optionNames as $name) {
            if (isset($queryOptions[$name])) {
                $this->values[$name] = $queryOptions[$name];
            } elseif (str_contains($name, '=')) {
                // Option avec valeur par défaut
                $parts = explode('=', $name);
                $this->values[$parts[0]] = $parts[1] ?? true;
            } else {
                // Option simple (flag)
                $this->values[$name] = false;
            }
        }
    }

    public function getValue(): StrictDataObject
    {
        return StrictDataObject::from($this->values);
    }

    public function has(string $name): bool
    {
        return isset($this->values[$name]) && $this->values[$name] !== false;
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->values[$name] ?? $default;
    }

    private function parseOptions(string $query): array
    {
        $parts = explode(' ', $query);
        $result = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                // Option avec valeur: --role=admin
                if (str_contains($part, '=')) {
                    [$key, $value] = explode('=', substr($part, 2), 2);
                    $result[$key] = $value;
                } else {
                    // Option flag: --force
                    $result[substr($part, 2)] = true;
                }
            }
        }

        return $result;
    }
}
