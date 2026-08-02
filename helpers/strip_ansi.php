<?php

declare(strict_types=1);

if (! function_exists('strip_ansi')) {
    /**
     * Remove ANSI escape sequences from a string.
     *
     * ANSI escape sequences are used to add color and styling to console output.
     * This function removes them, returning plain text.
     *
     * @param  string  $text  The text containing ANSI escape sequences
     * @return string The text without ANSI escape sequences
     */
    function strip_ansi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]+m/', '', $text);
    }
}
