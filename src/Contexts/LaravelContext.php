<?php
// src/Contexts/LaravelContext.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contexts;

use AndyDefer\Directive\Records\LaravelServiceRecord;
use AndyDefer\Directive\Collections\LaravelServiceCollection;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class LaravelContext
{
    private bool $isBootstrapped = false;
    private ?string $appEnvironment = null;
    private bool $isDebug = false;
    private LaravelServiceCollection $registeredServices;

    public function __construct()
    {
        $this->registeredServices = new LaravelServiceCollection();
    }

    public function isBootstrapped(): bool
    {
        return $this->isBootstrapped;
    }

    public function setIsBootstrapped(bool $bootstrapped): void
    {
        $this->isBootstrapped = $bootstrapped;
    }

    public function getAppEnvironment(): ?string
    {
        return $this->appEnvironment;
    }

    public function setAppEnvironment(string $environment): void
    {
        $this->appEnvironment = $environment;
    }

    public function isDebug(): bool
    {
        return $this->isDebug;
    }

    public function setIsDebug(bool $debug): void
    {
        $this->isDebug = $debug;
    }

    public function addRegisteredService(string $serviceName, string $alias): void
    {
        $this->registeredServices->add(new LaravelServiceRecord($serviceName, $alias, new DateTimeVO(null)));
    }

    public function getRegisteredServices(): LaravelServiceCollection
    {
        return $this->registeredServices;
    }

    public function hasService(string $serviceName): bool
    {
        return $this->registeredServices->containsServiceName($serviceName);
    }

    public function reset(): void
    {
        $this->isBootstrapped = false;
        $this->appEnvironment = null;
        $this->isDebug = false;
        $this->registeredServices = new LaravelServiceCollection();
    }
}
