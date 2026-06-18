<?php

// src/Services/ContainerService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\ContainerInterface;

final class ContainerService implements ContainerInterface
{
    private array $items = [];

    public function add(string $key, mixed $value): self
    {
        $this->items[$key] = $value;

        return $this;
    }

    public function addMany(array $items): self
    {
        foreach ($items as $key => $value) {
            $this->add($key, $value);
        }

        return $this;
    }

    public function remove(string $key): self
    {
        unset($this->items[$key]);

        return $this;
    }

    public function removeMany(array $keys): self
    {
        foreach ($keys as $key) {
            $this->remove($key);
        }

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function getAll(): array
    {
        return $this->items;
    }

    public function clear(): self
    {
        $this->items = [];

        return $this;
    }

    public function getContainer(): array
    {
        return $this->items;
    }
}
