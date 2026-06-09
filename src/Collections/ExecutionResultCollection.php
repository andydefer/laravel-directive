<?php
// src/Collections/ExecutionResultCollection.php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\ExecutionResultRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\Directive\Enums\ExitCode;

final class ExecutionResultCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ExecutionResultRecord::class);
    }

    public function getByDirectiveClass(string $directive_class): ?ExecutionResultRecord
    {
        foreach ($this->items as $result) {
            if ($result->directive_class === $directive_class) {
                return $result;
            }
        }

        return null;
    }

    public function getSuccessful(): self
    {
        return $this->filter(function (ExecutionResultRecord $record) {
            $exit_code = $record->result->exitCode ?? null;
            return $exit_code === ExitCode::SUCCESS->value;
        });
    }

    public function getFailed(): self
    {
        return $this->filter(function (ExecutionResultRecord $record) {
            $exit_code = $record->result->exitCode ?? null;
            return $exit_code !== ExitCode::SUCCESS->value;
        });
    }

    public function getByExitCode(int $exit_code): self
    {
        return $this->filter(function (ExecutionResultRecord $record) use ($exit_code) {
            return ($record->result->exitCode ?? null) === $exit_code;
        });
    }

    public function getWithOutput(): self
    {
        return $this->filter(fn(ExecutionResultRecord $record) => !empty($record->result->output ?? ''));
    }

    public function getWithoutOutput(): self
    {
        return $this->filter(fn(ExecutionResultRecord $record) => empty($record->result->output ?? ''));
    }
}
