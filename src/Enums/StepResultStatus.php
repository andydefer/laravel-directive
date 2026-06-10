<?php
// src/Enums/StepResultStatus.php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

/**
 * Enum representing the possible status of a step execution result.
 *
 * Each step in the testing initialization process returns a status
 * indicating whether it succeeded, failed, or was skipped.
 *
 * @author Andy Defer
 */
enum StepResultStatus: string
{
    /**
     * Step executed successfully.
     */
    case SUCCESS = 'success';

    /**
     * Step execution failed.
     */
    case FAILED = 'failed';

    /**
     * Step was skipped (not applicable).
     */
    case SKIPPED = 'skipped';

    /**
     * Step is still in progress.
     */
    case IN_PROGRESS = 'in_progress';

    /**
     * Step is pending execution.
     */
    case PENDING = 'pending';

    /**
     * Get the human-readable label for the status.
     *
     * @return string Human-readable label
     */
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

    /**
     * Check if the status represents a successful execution.
     *
     * @return bool True if successful
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * Check if the status represents a failed execution.
     *
     * @return bool True if failed
     */
    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Check if the status represents a skipped execution.
     *
     * @return bool True if skipped
     */
    public function isSkipped(): bool
    {
        return $this === self::SKIPPED;
    }

    /**
     * Check if the status is terminal (no further steps expected).
     *
     * @return bool True if terminal
     */
    public function isTerminal(): bool
    {
        return $this === self::SUCCESS || $this === self::FAILED;
    }

    /**
     * Create from boolean value.
     *
     * @param bool $success True for success, false for failed
     * @return self
     */
    public static function fromBoolean(bool $success): self
    {
        return $success ? self::SUCCESS : self::FAILED;
    }
}
