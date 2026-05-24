<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Enums\ShortOption;
use AndyDefer\Directive\Records\ValidationResultRecord;

/**
 * Validates directive signatures for format compliance.
 */
class SignatureValidationService
{
    private const PATTERN = '/^[a-zA-Z][a-zA-Z0-9-]*$/';

    public function validate(string $signature): ValidationResultRecord
    {
        if ($signature === '') {
            return new ValidationResultRecord(
                isValid: false,
                error: 'Directive name cannot be empty'
            );
        }

        // Accept long options like --help, --list
        if (str_starts_with($signature, '--')) {
            return new ValidationResultRecord(isValid: true, error: null);
        }

        // Accept specific short options - delegated to ShortOption enum
        if (ShortOption::isValid($signature)) {
            return new ValidationResultRecord(isValid: true, error: null);
        }

        if (! preg_match(self::PATTERN, $signature)) {
            return new ValidationResultRecord(
                isValid: false,
                error: sprintf(
                    'Invalid directive name: "%s". Use only letters, numbers, and hyphens. Must start with a letter. No spaces. Examples: user-create, clean-log, db-migrate-fresh',
                    $signature
                )
            );
        }

        // Check for consecutive hyphens
        if (str_contains($signature, '--')) {
            return new ValidationResultRecord(
                isValid: false,
                error: sprintf(
                    'Invalid directive name: "%s". Cannot have consecutive hyphens',
                    $signature
                )
            );
        }

        // Check for trailing hyphen
        if (str_ends_with($signature, '-')) {
            return new ValidationResultRecord(
                isValid: false,
                error: sprintf(
                    'Invalid directive name: "%s". Cannot end with a hyphen',
                    $signature
                )
            );
        }

        return new ValidationResultRecord(isValid: true, error: null);
    }
}
