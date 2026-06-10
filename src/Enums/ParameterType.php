<?php

// src/Enums/ParameterType.php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

/**
 * Defines the type of a directive parameter.
 *
 * - ARGUMENT: Positional parameter like {name} or {age}
 * - VARIADIC_ARGUMENT: Variable-length argument like {files*} capturing all remaining arguments
 * - OPTION: Named parameter like --verbose or --active
 */
enum ParameterType: string
{
    case ARGUMENT = 'argument';
    case VARIADIC_ARGUMENT = 'variadic_argument';
    case OPTION = 'option';

    /**
     * Check if this is an argument type (regular or variadic).
     */
    public function isArgument(): bool
    {
        return $this === self::ARGUMENT || $this === self::VARIADIC_ARGUMENT;
    }

    /**
     * Check if this is a regular (non-variadic) argument type.
     */
    public function isRegularArgument(): bool
    {
        return $this === self::ARGUMENT;
    }

    /**
     * Check if this is a variadic argument type.
     */
    public function isVariadicArgument(): bool
    {
        return $this === self::VARIADIC_ARGUMENT;
    }

    /**
     * Check if this is an option type.
     */
    public function isOption(): bool
    {
        return $this === self::OPTION;
    }

    /**
     * Get human-readable label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::ARGUMENT => 'Argument',
            self::VARIADIC_ARGUMENT => 'Variadic Argument',
            self::OPTION => 'Option',
        };
    }
}
