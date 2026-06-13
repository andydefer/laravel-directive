<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contexts\FileCreationContext;

/**
 * Service for converting string case formats.
 *
 * Provides bidirectional conversion between common naming conventions:
 * - PascalCase: UserProfile, SendWelcomeEmailTask
 * - Kebab-case: user-profile, send-welcome-email-task
 * - snake_case: user_profile, send_welcome_email_task
 *
 * @author Andy Defer
 */
class StringCaseConverterService
{
    /**
     * Convert a string from kebab-case or snake_case to PascalCase.
     *
     * @param string $string Input string in kebab-case or snake_case
     * @return string Converted string in PascalCase
     *
     * @example
     * // "user-profile" → "UserProfile"
     * // "user_profile" → "UserProfile"
     * // "send-welcome-email-task" → "SendWelcomeEmailTask"
     */
    public function toPascalCase(string $string): string
    {
        $result = str_replace(['-', '_'], ' ', $string);
        $result = ucwords($result);
        return str_replace(' ', '', $result);
    }

    /**
     * Convert a string from PascalCase to kebab-case.
     *
     * @param string $string Input string in PascalCase
     * @return string Converted string in kebab-case
     *
     * @example
     * // "UserProfile" → "user-profile"
     * // "SendWelcomeEmailTask" → "send-welcome-email-task"
     */
    public function toKebabCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $string));
    }

    /**
     * Convert a string from PascalCase to snake_case.
     *
     * @param string $string Input string in PascalCase
     * @return string Converted string in snake_case
     *
     * @example
     * // "UserProfile" → "user_profile"
     * // "SendWelcomeEmailTask" → "send_welcome_email_task"
     */
    public function toSnakeCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $string));
    }

    /**
     * Convert a string from kebab-case to snake_case.
     *
     * @param string $string Input string in kebab-case
     * @return string Converted string in snake_case
     */
    public function kebabToSnake(string $string): string
    {
        return str_replace('-', '_', $string);
    }

    /**
     * Convert a string from snake_case to kebab-case.
     *
     * @param string $string Input string in snake_case
     * @return string Converted string in kebab-case
     */
    public function snakeToKebab(string $string): string
    {
        return str_replace('_', '-', $string);
    }
}
