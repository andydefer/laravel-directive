<?php
// src/Enums/TestingStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

/**
 * Enum representing the possible steps in the directive testing initialization process.
 *
 * These steps are executed in a specific order by the Chain of Responsibility pattern.
 * Each step is responsible for a specific part of the test environment setup.
 *
 * @author Andy Defer
 */
enum TestingStep: string
{
    /**
     * Create temporary directory for tests.
     *
     * Creates a unique directory in the system's temporary folder.
     * This directory is used as an isolated sandbox for all test operations.
     */
    case CREATE_TEMP_DIRECTORY = 'create_temp_directory';

    /**
     * Change working directory to the temporary directory.
     *
     * Changes the current working directory (getcwd()) to the temporary directory.
     * The original directory is stored in the context for later restoration.
     */
    case CHANGE_TO_TEMP_DIRECTORY = 'change_to_temp_directory';

    /**
     * Create minimal Laravel application structure.
     *
     * Creates the necessary directories and files for a minimal Laravel application:
     * - bootstrap/app.php
     * - config/app.php
     * - storage/ directory structure
     * - app/ directory structure
     */
    case CREATE_LARAVEL_STRUCTURE = 'create_laravel_structure';

    /**
     * Bootstrap Laravel application.
     *
     * Loads and initializes the Laravel application instance.
     * This step is only executed if bootLaravel is enabled.
     */
    case BOOTSTRAP_LARAVEL = 'bootstrap_laravel';

    /**
     * Build the service container for testing.
     *
     * Creates and configures all necessary services:
     * - RenderDispatcher
     * - InputDispatcher
     * - DirectiveInteractionService
     * - DirectiveHydratorService
     * - DirectiveDiscoveryService
     * - DirectiveExecutionService
     * - DirectiveKernel
     */
    case BUILD_CONTAINER = 'build_container';

    /**
     * Get the human-readable label for the step.
     *
     * @return string Human-readable label
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::CREATE_TEMP_DIRECTORY => 'Create temporary directory',
            self::CHANGE_TO_TEMP_DIRECTORY => 'Change to temporary directory',
            self::CREATE_LARAVEL_STRUCTURE => 'Create Laravel structure',
            self::BOOTSTRAP_LARAVEL => 'Bootstrap Laravel',
            self::BUILD_CONTAINER => 'Build service container',
        };
    }

    /**
     * Get the description of what the step does.
     *
     * @return string Step description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::CREATE_TEMP_DIRECTORY => 'Creates an isolated temporary directory for test execution',
            self::CHANGE_TO_TEMP_DIRECTORY => 'Changes the working directory to the temporary directory',
            self::CREATE_LARAVEL_STRUCTURE => 'Creates minimal Laravel application structure (bootstrap, config, storage)',
            self::BOOTSTRAP_LARAVEL => 'Bootstraps the Laravel application instance',
            self::BUILD_CONTAINER => 'Builds the service container with all directive dependencies',
        };
    }

    /**
     * Get the expected execution order (1-based).
     *
     * @return int Execution order
     */
    public function getOrder(): int
    {
        return match ($this) {
            self::CREATE_TEMP_DIRECTORY => 1,
            self::CHANGE_TO_TEMP_DIRECTORY => 2,
            self::CREATE_LARAVEL_STRUCTURE => 3,
            self::BOOTSTRAP_LARAVEL => 4,
            self::BUILD_CONTAINER => 5,
        };
    }

    /**
     * Check if this step requires Laravel to be enabled.
     *
     * @return bool True if the step requires Laravel
     */
    public function requiresLaravel(): bool
    {
        return match ($this) {
            self::CREATE_LARAVEL_STRUCTURE,
            self::BOOTSTRAP_LARAVEL => true,
            default => false,
        };
    }

    /**
     * Get all steps in execution order.
     *
     * @return array<self> Ordered list of all steps
     */
    public static function getOrderedSteps(): array
    {
        return [
            self::CREATE_TEMP_DIRECTORY,
            self::CHANGE_TO_TEMP_DIRECTORY,
            self::CREATE_LARAVEL_STRUCTURE,
            self::BOOTSTRAP_LARAVEL,
            self::BUILD_CONTAINER,
        ];
    }

    /**
     * Get steps that are executed only when Laravel is enabled.
     *
     * @return array<self> Steps that require Laravel
     */
    public static function getLaravelSteps(): array
    {
        return [
            self::CREATE_LARAVEL_STRUCTURE,
            self::BOOTSTRAP_LARAVEL,
        ];
    }

    /**
     * Get the step from its string value.
     *
     * @param string $value Step string value
     * @return self|null Step or null if not found
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
