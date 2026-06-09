<?php
// src/Collections/FileOperationCollection.php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\FileOperationRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;

final class FileOperationCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(FileOperationRecord::class);
    }

    public function getByOperation(string $operation): self
    {
        return $this->filter(fn(FileOperationRecord $r) => $r->operation === $operation);
    }

    public function getWrites(): self
    {
        return $this->getByOperation('write');
    }

    public function getReads(): self
    {
        return $this->getByOperation('read');
    }

    public function getDirectoryCreations(): self
    {
        return $this->getByOperation('create_directory');
    }
}
