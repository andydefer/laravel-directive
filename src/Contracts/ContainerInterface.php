<?php

// src/Contracts/ContainerInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

interface ContainerInterface
{
    public function add(string $key, mixed $value): self;

    public function addMany(array $items): self;

    public function remove(string $key): self;

    public function removeMany(array $keys): self;

    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function getAll(): array;

    public function clear(): self;

    public function getContainer(): array;
}
