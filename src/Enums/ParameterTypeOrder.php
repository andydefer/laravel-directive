<?php
// src/Enums/ParameterTypeOrder.php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

/**
 * Enum representing the order of parameter types in a directive signature.
 *
 * Defines the strict order that parameters must follow:
 * 1. Required arguments
 * 2. Arguments with default values
 * 3. Optional arguments
 * 4. Variadic arguments
 * 5. Options
 */
enum ParameterTypeOrder: int
{
    case REQUIRED = 1;
    case DEFAULT = 2;
    case OPTIONAL = 3;
    case VARIADIC = 4;
    case OPTION = 5;

    /**
     * Get the human-readable label for this parameter type.
     *
     * @return string The display label
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::REQUIRED => 'Required arguments',
            self::DEFAULT => 'Arguments with default values',
            self::OPTIONAL => 'Optional arguments',
            self::VARIADIC => 'Variadic arguments',
            self::OPTION => 'Options',
        };
    }

    /**
     * Get the singular label for this parameter type.
     *
     * @return string The singular display label
     */
    public function getSingularLabel(): string
    {
        return match ($this) {
            self::REQUIRED => 'Required argument',
            self::DEFAULT => 'Argument with default value',
            self::OPTIONAL => 'Optional argument',
            self::VARIADIC => 'Variadic argument',
            self::OPTION => 'Option',
        };
    }

    /**
     * Check if this type comes before another in the order.
     *
     * @param self $other The other type to compare
     * @return bool True if this type comes before the other
     */
    public function comesBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    /**
     * Check if this type comes after another in the order.
     *
     * @param self $other The other type to compare
     * @return bool True if this type comes after the other
     */
    public function comesAfter(self $other): bool
    {
        return $this->value > $other->value;
    }
}
