<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

use AndyDefer\Records\Traits\Enumable;

/**
 * Defines the type of a directive parameter.
 *
 * - ARGUMENT: Positional parameter like {name} or {age}
 * - OPTION: Named parameter like --verbose or --active
 */
enum ParameterType: string
{
    use Enumable;
    case ARGUMENT = 'argument';
    case OPTION = 'option';

    /**
     * Check if this is an argument type.
     */
    public function isArgument(): bool
    {
        return $this === self::ARGUMENT;
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
            self::OPTION => 'Option',
        };
    }
}
