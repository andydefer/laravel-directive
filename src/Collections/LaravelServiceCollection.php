<?php

// src/Collections/LaravelServiceCollection.php

declare(strict_types=1);

namespace AndyDefer\Directive\Collections;

use AndyDefer\Directive\Records\LaravelServiceRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;

final class LaravelServiceCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(LaravelServiceRecord::class);
    }

    public function getByServiceName(string $serviceName): ?LaravelServiceRecord
    {
        foreach ($this->items as $record) {
            if ($record->serviceName === $serviceName) {
                return $record;
            }
        }

        return null;
    }

    public function containsServiceName(string $serviceName): bool
    {
        return $this->getByServiceName($serviceName) !== null;
    }
}
