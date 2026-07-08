<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

/**
 * Exit codes for directive execution.
 *
 * These codes follow standard Unix exit code conventions where 0 indicates
 * success and non-zero values indicate various error conditions.
 */
enum ExitCode: int
{
    case SUCCESS = 0;
    case FAILURE = 1;
    case INVALID_ARGUMENT = 2;
    case NOT_FOUND = 3;
    case PERMISSION_DENIED = 4;
    case RUNTIME_ERROR = 5;
    case INVALID_SIGNATURE = 6;
    case CONFLICT = 7;
    case DEPENDENCY_ERROR = 8;

    /**
     * Gets the human-readable label for this exit code.
     *
     * @return string The label
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::SUCCESS => 'Success',
            self::FAILURE => 'Failure',
            self::INVALID_ARGUMENT => 'Invalid Argument',
            self::NOT_FOUND => 'Not Found',
            self::PERMISSION_DENIED => 'Permission Denied',
            self::RUNTIME_ERROR => 'Runtime Error',
            self::INVALID_SIGNATURE => 'Invalid Signature',
            self::CONFLICT => 'Conflict',
            self::DEPENDENCY_ERROR => 'Dependency Error',
        };
    }

    /**
     * Checks if the exit code represents success.
     *
     * @return bool True if successful, false otherwise
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * Checks if the exit code represents failure.
     *
     * @return bool True if failed, false otherwise
     */
    public function isFailure(): bool
    {
        return $this !== self::SUCCESS;
    }

    /**
     * Checks if the exit code represents a "not found" error.
     *
     * @return bool True if the error is "not found", false otherwise
     */
    public function isNotFound(): bool
    {
        return $this === self::NOT_FOUND;
    }

    /**
     * Checks if the exit code represents an invalid argument error.
     *
     * @return bool True if the error is "invalid argument", false otherwise
     */
    public function isInvalidArgument(): bool
    {
        return $this === self::INVALID_ARGUMENT;
    }

    /**
     * Checks if the exit code represents a permission denied error.
     *
     * @return bool True if the error is "permission denied", false otherwise
     */
    public function isPermissionDenied(): bool
    {
        return $this === self::PERMISSION_DENIED;
    }

    /**
     * Checks if the exit code represents a runtime error.
     *
     * @return bool True if the error is "runtime error", false otherwise
     */
    public function isRuntimeError(): bool
    {
        return $this === self::RUNTIME_ERROR;
    }

    /**
     * Checks if the exit code represents an invalid signature error.
     *
     * @return bool True if the error is "invalid signature", false otherwise
     */
    public function isInvalidSignature(): bool
    {
        return $this === self::INVALID_SIGNATURE;
    }

    /**
     * Checks if the exit code represents a conflict error.
     *
     * @return bool True if the error is "conflict", false otherwise
     */
    public function isConflict(): bool
    {
        return $this === self::CONFLICT;
    }

    /**
     * Checks if the exit code represents a dependency error.
     *
     * @return bool True if the error is "dependency error", false otherwise
     */
    public function isDependencyError(): bool
    {
        return $this === self::DEPENDENCY_ERROR;
    }
}
