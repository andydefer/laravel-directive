<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Configs\SignatureValidationConfigInterface;
use AndyDefer\Directive\Enums\ShortOption;
use AndyDefer\Directive\Records\ValidationResultRecord;

/**
 * Validates directive signatures for format compliance.
 *
 * Ensures directive names follow the expected format:
 * - Starts with a letter
 * - Contains only letters, numbers, and hyphens
 * - No consecutive hyphens
 * - Does not end with a hyphen
 *
 * Also accepts special cases like long options (--help) and short options (-v).
 */
class SignatureValidationService
{
    public function __construct(
        private readonly SignatureValidationConfigInterface $config,
    ) {}

    /**
     * Validate a directive signature.
     *
     * @param  string  $signature  The directive signature to validate
     * @return ValidationResultRecord Contains validation status and error message if invalid
     */
    public function validate(string $signature): ValidationResultRecord
    {
        if ($this->isEmptySignature($signature)) {
            return $this->createInvalidResult($this->config->errorEmpty());
        }

        if ($this->isSpecialOption($signature)) {
            return $this->createValidResult();
        }

        if (! $this->matchesAllowedPattern($signature)) {
            return $this->createInvalidResult(sprintf($this->config->errorInvalidFormat(), $signature));
        }

        if ($this->hasConsecutiveHyphens($signature)) {
            return $this->createInvalidResult(sprintf($this->config->errorConsecutiveHyphens(), $signature));
        }

        if ($this->endsWithHyphen($signature)) {
            return $this->createInvalidResult(sprintf($this->config->errorTrailingHyphen(), $signature));
        }

        return $this->createValidResult();
    }

    /**
     * Check if the signature is empty.
     */
    private function isEmptySignature(string $signature): bool
    {
        return $signature === '';
    }

    /**
     * Check if the signature is a special option (long or short option).
     */
    private function isSpecialOption(string $signature): bool
    {
        return $this->isLongOption($signature) || ShortOption::isValid($signature);
    }

    /**
     * Check if the signature is a long option (e.g., --help).
     */
    private function isLongOption(string $signature): bool
    {
        return preg_match($this->config->longOptionPattern(), $signature) === 1;
    }

    /**
     * Check if the signature matches the allowed pattern for directive names.
     */
    private function matchesAllowedPattern(string $signature): bool
    {
        return (bool) preg_match($this->config->validNamePattern(), $signature);
    }

    /**
     * Check if the signature contains consecutive hyphens.
     */
    private function hasConsecutiveHyphens(string $signature): bool
    {
        return str_contains($signature, $this->config->longOptionPrefix());
    }

    /**
     * Check if the signature ends with a hyphen.
     */
    private function endsWithHyphen(string $signature): bool
    {
        return str_ends_with($signature, '-');
    }

    /**
     * Create a valid validation result.
     */
    private function createValidResult(): ValidationResultRecord
    {
        return new ValidationResultRecord(
            isValid: true,
            error: null,
        );
    }

    /**
     * Create an invalid validation result with an error message.
     */
    private function createInvalidResult(string $error): ValidationResultRecord
    {
        return new ValidationResultRecord(
            isValid: false,
            error: $error,
        );
    }
}
