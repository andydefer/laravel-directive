<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Configs;

/**
 * Interface for Directive configuration management.
 *
 * Defines the contract for accessing and manipulating directive configuration
 * values from the Laravel configuration repository.
 */
interface DirectiveConfigInterface
{
    /**
     * Gets the base path of the application.
     *
     * @return string The base path
     *
     * @throws \RuntimeException If the base path cannot be determined
     */
    public function basePath(): string;

    /**
     * Gets the directories to scan for directives.
     *
     * @return array<int, string> The list of directories
     */
    public function getDirectories(): array;

    /**
     * Gets the reserved signatures that cannot be used as directive names.
     *
     * @return array<int, string> The list of reserved signatures
     */
    public function getReservedSignatures(): array;

    /**
     * Sets the reserved signatures.
     *
     * @param  array<int, string>  $signatures  The list of reserved signatures
     */
    public function setReservedSignatures(array $signatures): void;

    /**
     * Gets the vendor directory path.
     *
     * @return string The vendor directory path
     */
    public function getVendorDir(): string;

    /**
     * Gets the composer.json file path.
     *
     * @return string The composer.json path
     */
    public function getComposerPath(): string;

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool True if debug mode is enabled, false otherwise
     */
    public function isDebug(): bool;

    /**
     * Gets the maximum directory scanning depth.
     *
     * @return int The maximum depth
     */
    public function getMaxDepth(): int;

    /**
     * Gets the custom sources to scan for directives.
     *
     * @return array<int, string> The list of custom source paths
     */
    public function getCustomSources(): array;
}
