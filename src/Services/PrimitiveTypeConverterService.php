<?php

namespace AndyDefer\Directive\Services;

use AndyDefer\PhpServices\Contracts\PrimitiveTypeConverterInterface;
use AndyDefer\PhpServices\Enums\PrimitiveType;
use InvalidArgumentException;

/**
 * @deprecated Ce service est déprécié. Utilisez celui du package andydefer/php-services à la place.
 *
 * Ce service sera supprimé dans la version 2.0.0 de ce package.
 *
 * ❌ À NE PLUS UTILISER :
 * - PrimitiveTypeConverterService::convert()
 * - PrimitiveTypeConverterService::convertOrDefault()
 * - PrimitiveTypeConverterService::detectType()
 *
 * ✅ RECOMMANDATION :
 * Utilisez le service du package andydefer/php-services qui offre les mêmes fonctionnalités
 * avec une meilleure intégration et plus de stabilité.
 *
 * Installer d'abord le package :
 * composer require andydefer/php-services
 *
 * Ensuite, utilisez le service :
 *
 * use AndyDefer\PhpServices\Services\PrimitiveTypeConverterService;
 * use AndyDefer\PhpServices\Enums\PrimitiveType;
 *
 * $converter = app(PrimitiveTypeConverterInterface::class);
 * // ou
 * $converter = new PrimitiveTypeConverterService();
 *
 * // Conversion
 * $intValue = $converter->convert('123', PrimitiveType::INT);
 *
 * // Conversion avec valeur par défaut
 * $safeValue = $converter->convertOrDefault('invalid', PrimitiveType::INT, 0);
 *
 * // Détection de type
 * $type = $converter->detectType($value);
 * @see \AndyDefer\PhpServices\Services\PrimitiveTypeConverterService
 * @see PrimitiveTypeConverterInterface
 * @deprecated
 */
class PrimitiveTypeConverterService
{
    /**
     * @deprecated Utilisez AndyDefer\PhpServices\Services\PrimitiveTypeConverterService::convert()
     *
     * ✅ NOUVELLE APPROCHE :
     * $converter = app(PrimitiveTypeConverterInterface::class);
     * $result = $converter->convert($value, PrimitiveType::INT);
     */
    public function convert(mixed $value, PrimitiveType $targetType): mixed
    {
        @trigger_error(
            sprintf(
                '%s::convert() est dépréciée. Utilisez %s::convert() du package andydefer/php-services à la place.',
                __CLASS__,
                \AndyDefer\PhpServices\Services\PrimitiveTypeConverterService::class
            ),
            E_USER_DEPRECATED
        );

        return match ($targetType) {
            PrimitiveType::BOOL => (bool) $value,
            PrimitiveType::STRING => (string) $value,
            PrimitiveType::INT => (int) $value,
            PrimitiveType::FLOAT => (float) $value,
            PrimitiveType::NULL => null,
        };
    }

    /**
     * @deprecated Utilisez AndyDefer\PhpServices\Services\PrimitiveTypeConverterService::convertOrDefault()
     *
     * ✅ NOUVELLE APPROCHE :
     * $converter = app(PrimitiveTypeConverterInterface::class);
     * $result = $converter->convertOrDefault($value, PrimitiveType::INT, 0);
     */
    public function convertOrDefault(mixed $value, PrimitiveType $targetType, mixed $default = null): mixed
    {
        @trigger_error(
            sprintf(
                '%s::convertOrDefault() est dépréciée. Utilisez %s::convertOrDefault() du package andydefer/php-services à la place.',
                __CLASS__,
                \AndyDefer\PhpServices\Services\PrimitiveTypeConverterService::class
            ),
            E_USER_DEPRECATED
        );

        try {
            return $this->convert($value, $targetType);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * @deprecated Utilisez AndyDefer\PhpServices\Services\PrimitiveTypeConverterService::detectType()
     *
     * ✅ NOUVELLE APPROCHE :
     * $converter = app(PrimitiveTypeConverterInterface::class);
     * $type = $converter->detectType($value);
     */
    public function detectType(mixed $value): PrimitiveType
    {
        @trigger_error(
            sprintf(
                '%s::detectType() est dépréciée. Utilisez %s::detectType() du package andydefer/php-services à la place.',
                __CLASS__,
                \AndyDefer\PhpServices\Services\PrimitiveTypeConverterService::class
            ),
            E_USER_DEPRECATED
        );

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
