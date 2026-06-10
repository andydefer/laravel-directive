<?php

// src/Contracts/Configs/SignatureValidationConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Configs;

interface SignatureValidationConfigInterface
{
    /**
     * Get the regex pattern for valid directive names.
     *
     * @return string Regex pattern (e.g., '/^[a-zA-Z][a-zA-Z0-9-]*$/')
     */
    public function validNamePattern(): string;

    /**
     * Get the error message for empty signature.
     *
     * @return string Error message
     */
    public function errorEmpty(): string;

    /**
     * Get the error message for invalid format.
     *
     * @return string Error message template with %s placeholder for the signature
     */
    public function errorInvalidFormat(): string;

    /**
     * Get the error message for consecutive hyphens.
     *
     * @return string Error message template with %s placeholder for the signature
     */
    public function errorConsecutiveHyphens(): string;

    /**
     * Get the error message for trailing hyphen.
     *
     * @return string Error message template with %s placeholder for the signature
     */
    public function errorTrailingHyphen(): string;

    /**
     * Get the prefix for long options.
     *
     * @return string Long option prefix (e.g., '--')
     */
    public function longOptionPrefix(): string;

    /**
     * Get the pattern for checking if a signature is a long option.
     *
     * @return string Long option pattern (e.g., '/^--/')
     */
    public function longOptionPattern(): string;
}
