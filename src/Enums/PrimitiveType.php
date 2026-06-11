<?php

namespace AndyDefer\Directive\Enums;

/**
 * Enum représentant les types primitifs PHP (boolean, string, integer, NULL, double).
 */
enum PrimitiveType: string
{
    case BOOL = 'boolean';
    case STRING = 'string';
    case INT = 'integer';
    case NULL = 'NULL';
    case FLOAT = 'double';

    /**
     * Crée l'enum à partir d'une valeur PHP.
     */
    public static function fromValue(mixed $value): ?self
    {
        return self::tryFrom(gettype($value));
    }

    /**
     * Vérifie si une valeur est du type de l'enum.
     */
    public function matches(mixed $value): bool
    {
        return gettype($value) === $this->value;
    }

    /**
     * Retourne le label formaté pour l'affichage (bool|string|int|null|float).
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::BOOL => 'bool',
            self::STRING => 'string',
            self::INT => 'int',
            self::NULL => 'null',
            self::FLOAT => 'float',
        };
    }

    /**
     * Retourne tous les types scalaires acceptés.
     */
    public static function getAcceptedTypes(): array
    {
        return [
            self::BOOL,
            self::STRING,
            self::INT,
            self::NULL,
            self::FLOAT,
        ];
    }

    /**
     * Retourne tous les labels des types acceptés.
     */
    public static function getAcceptedLabels(): string
    {
        return 'bool|string|int|null|float';
    }
}
