<?php
// src/Contracts/Configs/DirectiveParserConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Configs;

/**
 * Interface for directive parser configuration.
 *
 * @author Andy Defer
 */
interface DirectiveParserConfigInterface
{
    /**
     * Get the prefix for long options.
     *
     * @return string Long option prefix (e.g., '--')
     */
    public function longOptionPrefix(): string;

    /**
     * Get the prefix for short options.
     *
     * @return string Short option prefix (e.g., '-')
     */
    public function shortOptionPrefix(): string;

    /**
     * Get the string representation of true value.
     *
     * @return string True value representation
     */
    public function trueValue(): string;

    /**
     * Get the string representation of false value.
     *
     * @return string False value representation
     */
    public function falseValue(): string;

    /**
     * Determine if empty option values should be treated as true.
     *
     * @return bool True if empty options are treated as true
     */
    public function emptyOptionAsTrue(): bool;

    /**
     * Get the character used to separate option name from value.
     *
     * @return string Option value separator (e.g., '=')
     */
    public function optionValueSeparator(): string;

    /**
     * Get the character used to mark optional parameters.
     *
     * @return string Optional marker character
     */
    public function optionalMarker(): string;

    /**
     * Get the character used to mark variadic parameters.
     *
     * @return string Variadic marker character
     */
    public function variadicMarker(): string;
}
