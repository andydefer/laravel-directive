<?php
// src/Contexts/FileSystemContext.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contexts;

use AndyDefer\Directive\Records\FileOperationRecord;
use AndyDefer\Directive\Collections\FileOperationCollection;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class FileSystemContext
{
    private FileOperationCollection $fileOperations;
    private int $totalBytesWritten = 0;
    private int $totalFilesCreated = 0;
    private int $totalDirectoriesCreated = 0;

    public function __construct()
    {
        $this->fileOperations = new FileOperationCollection();
    }

    public function addFileOperation(string $operation, string $path, ?int $bytes = null): void
    {
        $record = new FileOperationRecord(
            operation: $operation,
            path: $path,
            bytes: $bytes,
            timestamp: new DateTimeVO(null),
        );
        $this->fileOperations->add($record);

        if ($operation === 'write' && $bytes !== null) {
            $this->totalBytesWritten += $bytes;
            $this->totalFilesCreated++;
        } elseif ($operation === 'create_directory') {
            $this->totalDirectoriesCreated++;
        }
    }

    public function getFileOperations(): FileOperationCollection
    {
        return $this->fileOperations;
    }

    public function getTotalBytesWritten(): int
    {
        return $this->totalBytesWritten;
    }

    public function getTotalFilesCreated(): int
    {
        return $this->totalFilesCreated;
    }

    public function getTotalDirectoriesCreated(): int
    {
        return $this->totalDirectoriesCreated;
    }

    public function reset(): void
    {
        $this->fileOperations = new FileOperationCollection();
        $this->totalBytesWritten = 0;
        $this->totalFilesCreated = 0;
        $this->totalDirectoriesCreated = 0;
    }
}
