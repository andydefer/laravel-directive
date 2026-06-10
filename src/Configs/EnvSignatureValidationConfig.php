<?php

// src/Configs/EnvSignatureValidationConfig.php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\Directive\Contracts\Configs\SignatureValidationConfigInterface;

final class EnvSignatureValidationConfig implements SignatureValidationConfigInterface
{
    public function validNamePattern(): string
    {
        return getenv('SIGNATURE_VALID_NAME_PATTERN') ?: '/^[a-zA-Z][a-zA-Z0-9-]*$/';
    }

    public function errorEmpty(): string
    {
        return getenv('SIGNATURE_ERROR_EMPTY') ?: 'Directive name cannot be empty';
    }

    public function errorInvalidFormat(): string
    {
        return getenv('SIGNATURE_ERROR_INVALID_FORMAT')
            ?: 'Invalid directive name: "%s". Use only letters, numbers, and hyphens. Must start with a letter. No spaces. Examples: user-create, clean-log, db-migrate-fresh';
    }

    public function errorConsecutiveHyphens(): string
    {
        return getenv('SIGNATURE_ERROR_CONSECUTIVE_HYPHENS')
            ?: 'Invalid directive name: "%s". Cannot have consecutive hyphens';
    }

    public function errorTrailingHyphen(): string
    {
        return getenv('SIGNATURE_ERROR_TRAILING_HYPHEN')
            ?: 'Invalid directive name: "%s". Cannot end with a hyphen';
    }

    public function longOptionPrefix(): string
    {
        return getenv('DIRECTIVE_LONG_OPTION_PREFIX') ?: '--';
    }

    public function longOptionPattern(): string
    {
        return getenv('SIGNATURE_LONG_OPTION_PATTERN') ?: '/^--/';
    }
}
