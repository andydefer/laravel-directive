<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Container;

/**
 * Complete Directive container with all services pre-registered.
 *
 * Use this for standalone applications or when you want a
 * ready-to-use container without Laravel.
 */
final class DirectiveContainer extends Container
{
    private DirectiveServiceRegistrar $registrar;

    protected function __construct(string $basePath = __DIR__)
    {
        parent::__construct($basePath);

        // Créer le registrar et enregistrer tous les services
        $this->registrar = new DirectiveServiceRegistrar($this);
        $this->registrar->registerAll();
    }

    public static function create(string $basePath = __DIR__): self
    {
        return new self($basePath);
    }
}
