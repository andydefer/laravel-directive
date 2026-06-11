<?php

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Enums\PrimitiveType;
use InvalidArgumentException;

/**
 * Service de conversion entre types primitifs PHP.
 */
class PrimitiveTypeConverterService
{
    /**
     * Convertit une valeur d'un type primitif vers un autre.
     *
     * @param  mixed  $value  La valeur à convertir
     * @param  PrimitiveType  $targetType  Le type cible
     * @return mixed La valeur convertie
     */
    public function convert(mixed $value, PrimitiveType $targetType): mixed
    {
        return match ($targetType) {
            PrimitiveType::BOOL => (bool) $value,
            PrimitiveType::STRING => (string) $value,
            PrimitiveType::INT => (int) $value,
            PrimitiveType::FLOAT => (float) $value,
            PrimitiveType::NULL => null,
        };
    }

    /**
     * Convertit une valeur en retournant une valeur par défaut si échec.
     */
    public function convertOrDefault(mixed $value, PrimitiveType $targetType, mixed $default = null): mixed
    {
        try {
            return $this->convert($value, $targetType);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Détecte le type primitif d'une valeur.
     *
     * @param  mixed  $value  La valeur à analyser
     * @return PrimitiveType Le type détecté
     *
     * @throws InvalidArgumentException Si la valeur n'est pas un type primitif PHP
     */
    public function detectType(mixed $value): PrimitiveType
    {
        return match (true) {
            $value === null => PrimitiveType::NULL,
            is_bool($value) => PrimitiveType::BOOL,
            is_int($value) => PrimitiveType::INT,
            is_float($value) => PrimitiveType::FLOAT,
            is_string($value) => PrimitiveType::STRING,
            default => throw new InvalidArgumentException(
                sprintf('Unable to detect type for value of type: %s %s', gettype($value), json_encode($value))
            ),
        };
    }
}
