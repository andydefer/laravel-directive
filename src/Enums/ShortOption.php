<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

enum ShortOption: string
{
    case HELP = 'h';
    case LIST = 'l';
    case VERBOSE = 'v';
    case QUIET = 'q';
    case FORCE = 'f';
    case DEBUG = 'd';
    case VERSION = 'V';

    /**
     * Get the display label for the short option.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::HELP => 'Help',
            self::LIST => 'List',
            self::VERBOSE => 'Verbose output',
            self::QUIET => 'Quiet mode',
            self::FORCE => 'Force execution',
            self::DEBUG => 'Debug mode',
            self::VERSION => 'Show version',
        };
    }

    /**
     * Get the corresponding long option for this short option.
     */
    public function getLongOption(): string
    {
        return match ($this) {
            self::HELP => 'help',
            self::LIST => 'list',
            self::VERBOSE => 'verbose',
            self::QUIET => 'quiet',
            self::FORCE => 'force',
            self::DEBUG => 'debug',
            self::VERSION => 'version',
        };
    }

    /**
     * Get the description for display in help.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::HELP => 'Show this help message',
            self::LIST => 'List all available directives',
            self::VERBOSE => 'Increase output verbosity',
            self::QUIET => 'Suppress all output',
            self::FORCE => 'Force operation without confirmation',
            self::DEBUG => 'Enable debug mode',
            self::VERSION => 'Display version information',
        };
    }

    /**
     * Get the full option string for display.
     */
    public function getDisplayString(): string
    {
        return '-' . $this->value . ', --' . $this->getLongOption();
    }

    /**
     * Get all allowed short option characters.
     *
     * @return array<string>
     */
    public static function getAllowedCharacters(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a character is an allowed short option.
     *
     * @param string $char The character to check
     * @return bool True if the character is an allowed short option
     */
    public static function isAllowed(string $char): bool
    {
        return in_array($char, self::getAllowedCharacters(), true);
    }

    /**
     * Validate and parse a short option string.
     *
     * @param string $shortOption The short option string (e.g., '-h', '-vl')
     * @return array<string>|null Array of valid option characters or null if invalid
     */
    public static function parse(string $shortOption): ?array
    {
        if (!str_starts_with($shortOption, '-') || str_starts_with($shortOption, '--')) {
            return null;
        }

        $option = ltrim($shortOption, '-');

        if ($option === '') {
            return null;
        }

        $chars = str_split($option);
        $validChars = [];

        foreach ($chars as $char) {
            if (!self::isAllowed($char)) {
                return null;
            }
            $validChars[] = $char;
        }

        return $validChars;
    }

    /**
     * Check if a short option string is valid.
     *
     * @param string $shortOption The short option string (e.g., '-h', '-vl')
     * @return bool True if all characters are allowed
     */
    public static function isValid(string $shortOption): bool
    {
        return self::parse($shortOption) !== null;
    }
}
