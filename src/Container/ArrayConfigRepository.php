<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Container;

use Illuminate\Contracts\Config\Repository;

/**
 * Simple array-based configuration repository implementation.
 *
 * Implements Laravel's Config Repository interface for standalone usage.
 */
final class ArrayConfigRepository implements Repository
{
    private array $items = [];

    /**
     * Create a new configuration repository instance.
     *
     * @param  array<string, mixed>  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set($k, $v);
            }

            return;
        }

        $segments = explode('.', $key);
        $target = &$this->items;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $target[$segment] = $value;
            } else {
                if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                    $target[$segment] = [];
                }
                $target = &$target[$segment];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function prepend($key, $value)
    {
        $current = $this->get($key, []);

        if (! is_array($current)) {
            $current = [];
        }

        array_unshift($current, $value);
        $this->set($key, $current);
    }

    /**
     * {@inheritdoc}
     */
    public function push($key, $value)
    {
        $current = $this->get($key, []);

        if (! is_array($current)) {
            $current = [];
        }

        $current[] = $value;
        $this->set($key, $current);
    }
}
