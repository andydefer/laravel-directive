<?php

namespace AndyDefer\Directive\Enums;

/**
 * @deprecated Cet enum est déprécié. Utilisez celui du package andydefer/php-services à la place.
 * 
 * Cet enum sera supprimé dans la version 2.0.0 de ce package.
 * 
 * ❌ À NE PLUS UTILISER :
 * - AndyDefer\Directive\Enums\PrimitiveType
 * 
 * ✅ RECOMMANDATION :
 * Utilisez l'enum du package andydefer/php-services qui offre les mêmes fonctionnalités
 * avec une meilleure intégration et plus de stabilité.
 * 
 * Installer d'abord le package :
 * composer require andydefer/php-services
 * 
 * Ensuite, utilisez l'enum :
 * 
 * use AndyDefer\PhpServices\Enums\PrimitiveType;
 * 
 * // Créer à partir d'une valeur
 * $type = PrimitiveType::fromValue($value);
 * 
 * // Vérifier si une valeur correspond
 * if ($type->matches($value)) { ... }
 * 
 * // Obtenir le label formaté
 * $label = $type->getLabel(); // 'bool', 'string', 'int', 'null', 'float'
 * 
 * // Obtenir tous les types acceptés
 * $types = PrimitiveType::getAcceptedTypes();
 * 
 * // Obtenir les labels des types acceptés
 * $labels = PrimitiveType::getAcceptedLabels(); // 'bool|string|int|null|float'
 * 
 * @see \AndyDefer\PhpServices\Enums\PrimitiveType
 * @deprecated
 */
enum PrimitiveType: string
{
    case BOOL = 'boolean';
    case STRING = 'string';
    case INT = 'integer';
    case NULL = 'NULL';
    case FLOAT = 'double';

    /**
     * @deprecated Utilisez AndyDefer\PhpServices\Enums\PrimitiveType::fromValue()
     * 
     * ✅ NOUVELLE APPROCHE :
     * $type = PrimitiveType::fromValue($value);
     */
    public static function fromValue(mixed $value): ?self
    {
        @trigger_error(
            sprintf(
                '%s::fromValue() est dépréciée. Utilisez %s::fromValue() du package andydefer/php-services à la place.',
                __CLASS__,
                \AndyDefer\PhpServices\Enums\PrimitiveType::class
            ),
            E_USER_DEPRECATED
        );

        return self::tryFrom(gettype($value));
    }

    /**
     * @deprecated Utilisez AndyDefer\PhpServices\Enums\PrimitiveType::matches()
     * 
     * ✅ NOUVELLE APPROCHE :
     * if ($type->matches($value)) { ... }
     */
    public function matches(mixed $value): bool
    {
        @trigger_error(
            sprintf(
                '%s::matches() est dépréciée. Utilisez %s::matches() du package andydefer/php-services à la place.',
                __CLASS__,
                \AndyDefer\PhpServices\Enums\PrimitiveType::class
            ),
            E_USER_DEPRECATED
        );

        return gettype($value) === $this->value;
    }

    /**
     * @deprecated Utilisez AndyDefer\PhpServices\Enums\PrimitiveType::getLabel()
     * 
     * ✅ NOUVELLE APPROCHE :
     * $label = $type->getLabel();
     */
    public function getLabel(): string
    {
        @trigger_error(
            sprintf(
                '%s::getLabel() est dépréciée. Utilisez %s::getLabel() du package andydefer/php-services à la place.',
                __CLASS__,
                \AndyDefer\PhpServices\Enums\PrimitiveType::class
            ),
            E_USER_DEPRECATED
        );

        return match ($this) {
            self::BOOL => 'bool',
            self::STRING => 'string',
            self::INT => 'int',
            self::NULL => 'null',
            self::FLOAT => 'float',
        };
    }

    /**
     * @deprecated Utilisez AndyDefer\PhpServices\Enums\PrimitiveType::getAcceptedTypes()
     * 
     * ✅ NOUVELLE APPROCHE :
     * $types = PrimitiveType::getAcceptedTypes();
     */
    public static function getAcceptedTypes(): array
    {
        @trigger_error(
            sprintf(
                '%s::getAcceptedTypes() est dépréciée. Utilisez %s::getAcceptedTypes() du package andydefer/php-services à la place.',
                __CLASS__,
                \AndyDefer\PhpServices\Enums\PrimitiveType::class
            ),
            E_USER_DEPRECATED
        );

        return [
            self::BOOL,
            self::STRING,
            self::INT,
            self::NULL,
            self::FLOAT,
        ];
    }

    /**
     * @deprecated Utilisez AndyDefer\PhpServices\Enums\PrimitiveType::getAcceptedLabels()
     * 
     * ✅ NOUVELLE APPROCHE :
     * $labels = PrimitiveType::getAcceptedLabels();
     */
    public static function getAcceptedLabels(): string
    {
        @trigger_error(
            sprintf(
                '%s::getAcceptedLabels() est dépréciée. Utilisez %s::getAcceptedLabels() du package andydefer/php-services à la place.',
                __CLASS__,
                \AndyDefer\PhpServices\Enums\PrimitiveType::class
            ),
            E_USER_DEPRECATED
        );

        return 'bool|string|int|null|float';
    }
}
