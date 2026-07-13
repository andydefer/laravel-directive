<?php

namespace AndyDefer\Directive\Bootstrap;

use AndyDefer\Directive\DirectiveServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Facades\Facade;

class InternalApplicationFactory
{
    public static function create(): Application
    {
        // ✅ Créer le dossier cache
        $cachePath = Paths::projectRoot().'/bootstrap/cache';
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        // ✅ Créer l'application SANS configuration DB
        $app = Application::configure(basePath: Paths::projectRoot())
            ->withExceptions(function (Exceptions $exceptions): void {
                //
            })
            ->create();

        // ✅ DÉFINIR LE FACADE ROOT
        Facade::setFacadeApplication($app);

        // ✅ Enregistrer UNIQUEMENT les providers nécessaires
        $app->register(DirectiveServiceProvider::class);

        return $app;
    }
}
