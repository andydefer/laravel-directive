<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

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

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isFailure(): bool
    {
        return $this !== self::SUCCESS;
    }

    public function isNotFound(): bool
    {
        return $this === self::NOT_FOUND;
    }

    public function isInvalidArgument(): bool
    {
        return $this === self::INVALID_ARGUMENT;
    }

    public function isPermissionDenied(): bool
    {
        return $this === self::PERMISSION_DENIED;
    }

    public function isRuntimeError(): bool
    {
        return $this === self::RUNTIME_ERROR;
    }

    public function isInvalidSignature(): bool
    {
        return $this === self::INVALID_SIGNATURE;
    }

    public function isConflict(): bool
    {
        return $this === self::CONFLICT;
    }

    public function isDependencyError(): bool
    {
        return $this === self::DEPENDENCY_ERROR;
    }
}
