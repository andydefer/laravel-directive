<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\LaravelBootstrapperInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;

/**
 * Test bootstrapper that uses existing Laravel application from context.
 */
class TestLaravelBootstrapper implements LaravelBootstrapperInterface
{
    private bool $bootstrapped = false;
    private ?object $application = null;
    private ?string $error = null;
    private static ?Capsule $capsule = null;

    public function setExistingApplication(object $application): self
    {
        $this->application = $application;

        // Configurer les Facades avec l'application existante
        Facade::setFacadeApplication($application);

        // S'assurer que l'application a les providers nécessaires
        if (method_exists($application, 'register')) {
            dump("Hello");
            if (!$application->registered(\Illuminate\Hashing\HashServiceProvider::class)) {
                $application->register(\Illuminate\Hashing\HashServiceProvider::class);
            }
            if (!$application->registered(\Illuminate\Database\DatabaseServiceProvider::class)) {
                $application->register(\Illuminate\Database\DatabaseServiceProvider::class);
            }
        }

        // Initialiser Eloquent une seule fois
        if (self::$capsule === null) {
            self::$capsule = new Capsule($application);
            self::$capsule->addConnection([
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
            self::$capsule->setAsGlobal();
            self::$capsule->bootEloquent();

            if (isset($application['events'])) {
                self::$capsule->setEventDispatcher($application['events']);
            }
        }

        $this->bootstrapped = true;
        return $this;
    }

    public function bootstrap(): bool
    {
        return $this->bootstrapped && $this->application !== null;
    }

    public function isBootstrapped(): bool
    {
        return $this->bootstrapped && $this->application !== null;
    }

    public function getApplication(): ?object
    {
        return $this->application;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function reset(): void
    {
        $this->bootstrapped = false;
        $this->application = null;
        $this->error = null;
        self::$capsule = null;
    }

    public static function getCapsule(): ?Capsule
    {
        return self::$capsule;
    }
}
