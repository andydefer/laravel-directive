<?php

// src/Enums/StepResultStatus.php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

enum StepResultStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case IN_PROGRESS = 'in_progress';
    case PENDING = 'pending';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::SKIPPED => 'Skipped',
            self::IN_PROGRESS => 'In Progress',
            self::PENDING => 'Pending',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function isSkipped(): bool
    {
        return $this === self::SKIPPED;
    }

    public function isTerminal(): bool
    {
        return $this === self::SUCCESS || $this === self::FAILED;
    }

    public static function fromBoolean(bool $success): self
    {
        return $success ? self::SUCCESS : self::FAILED;
    }
}
