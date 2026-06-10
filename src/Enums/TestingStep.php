<?php

// src/Enums/TestingStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

enum TestingStep: string
{
    case CREATE_TEMP_DIRECTORY = 'create_temp_directory';
    case CHANGE_TO_TEMP_DIRECTORY = 'change_to_temp_directory';
    case CREATE_LARAVEL_STRUCTURE = 'create_laravel_structure';
    case START_DATABASE = 'start_database';
    case BOOTSTRAP_LARAVEL = 'bootstrap_laravel';
    case BUILD_CONTAINER = 'build_container';

    public function getLabel(): string
    {
        return match ($this) {
            self::CREATE_TEMP_DIRECTORY => 'Create temporary directory',
            self::CHANGE_TO_TEMP_DIRECTORY => 'Change to temporary directory',
            self::CREATE_LARAVEL_STRUCTURE => 'Create Laravel structure',
            self::START_DATABASE => 'Start database',
            self::BOOTSTRAP_LARAVEL => 'Bootstrap Laravel',
            self::BUILD_CONTAINER => 'Build service container',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CREATE_TEMP_DIRECTORY => 'Creates an isolated temporary directory for test execution',
            self::CHANGE_TO_TEMP_DIRECTORY => 'Changes the working directory to the temporary directory',
            self::CREATE_LARAVEL_STRUCTURE => 'Creates minimal Laravel application structure (bootstrap, config, storage)',
            self::START_DATABASE => 'Initializes database connection (SQLite or MySQL)',
            self::BOOTSTRAP_LARAVEL => 'Bootstraps the Laravel application instance',
            self::BUILD_CONTAINER => 'Builds the service container with all directive dependencies',
        };
    }

    public function getOrder(): int
    {
        return match ($this) {
            self::CREATE_TEMP_DIRECTORY => 1,
            self::CHANGE_TO_TEMP_DIRECTORY => 2,
            self::CREATE_LARAVEL_STRUCTURE => 3,
            self::START_DATABASE => 4,
            self::BOOTSTRAP_LARAVEL => 5,
            self::BUILD_CONTAINER => 6,
        };
    }

    public function requiresLaravel(): bool
    {
        return match ($this) {
            self::CREATE_LARAVEL_STRUCTURE,
            self::START_DATABASE,
            self::BOOTSTRAP_LARAVEL => true,
            default => false,
        };
    }

    public static function getOrderedSteps(): array
    {
        return [
            self::CREATE_TEMP_DIRECTORY,
            self::CHANGE_TO_TEMP_DIRECTORY,
            self::CREATE_LARAVEL_STRUCTURE,
            self::START_DATABASE,
            self::BOOTSTRAP_LARAVEL,
            self::BUILD_CONTAINER,
        ];
    }

    public static function getLaravelSteps(): array
    {
        return [
            self::CREATE_LARAVEL_STRUCTURE,
            self::START_DATABASE,
            self::BOOTSTRAP_LARAVEL,
        ];
    }

    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
