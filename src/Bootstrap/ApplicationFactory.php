<?php

namespace AndyDefer\Directive\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ApplicationFactory
{
    /**
     * Create a new application builder
     */
    public static function builder(): ApplicationBuilder
    {
        return new ApplicationBuilder;
    }

    /**
     * Create application with providers (simple approach)
     *
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function create(array $providers = []): Application
    {
        return self::builder()
            ->withProviders($providers)
            ->build();
    }
}
