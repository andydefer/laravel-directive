<?php

// src/Collections/CreatedPathCollection.php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Enums\PathType;
use AndyDefer\Directive\Records\CreatedPathRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class CreatedPathCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(CreatedPathRecord::class);
    }

    public function getByPath(string $path): ?CreatedPathRecord
    {
        foreach ($this->items as $record) {
            if ($record->path === $path) {
                return $record;
            }
        }

        return null;
    }

    public function getByType(string $type): self
    {
        $collection = new self;
        foreach ($this->items as $record) {
            if ($record->type === $type) {
                $collection->add($record);
            }
        }

        return $collection;
    }

    public function getFiles(): self
    {
        return $this->getByType(PathType::FILE->value);
    }

    public function getDirectories(): self
    {
        return $this->getByType(PathType::DIRECTORY->value);
    }

    public function getCreatedAfter(DateTimeVO $date): self
    {
        $collection = new self;
        foreach ($this->items as $record) {
            if ($record->created_at->isAfter($date)) {
                $collection->add($record);
            }
        }

        return $collection;
    }

    public function getCreatedBefore(DateTimeVO $date): self
    {
        $collection = new self;
        foreach ($this->items as $record) {
            if ($record->created_at->isBefore($date)) {
                $collection->add($record);
            }
        }

        return $collection;
    }

    public function hasPath(string $path): bool
    {
        return $this->getByPath($path) !== null;
    }

    public function hasFile(string $path): bool
    {
        $record = $this->getByPath($path);

        return $record !== null && $record->type === PathType::FILE->value;
    }

    public function hasDirectory(string $path): bool
    {
        $record = $this->getByPath($path);

        return $record !== null && $record->type === PathType::DIRECTORY->value;
    }

    public function getPaths(): array
    {
        return array_map(fn (CreatedPathRecord $record) => $record->path, $this->items);
    }
}
