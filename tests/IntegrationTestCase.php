<?php

// tests/IntegrationTestCase.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use Carbon\Carbon;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected LaravelBootstrapper $laravelBootstrapper;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        // 1. Créer le bootstrapper avec le chemin personnalisé
        $this->laravelBootstrapper = new LaravelBootstrapper;
        $this->laravelBootstrapper->setCustomBootstrapPath(__DIR__.'/bootstrap/app.php');
        $this->app->instance(LaravelBootstrapper::class, $this->laravelBootstrapper);

        // 2. Bootstrap Laravel
        $success = $this->laravelBootstrapper->bootstrap();

        if (! $success) {
            $this->markTestSkipped('Laravel bootstrap failed: '.$this->laravelBootstrapper->getError());
        }

        // 3. Configurer le chemin des directives
        $this->configureDirectivesPath();

        // 4. 🔥 Plus besoin d'enregistrer les directives de test manuellement
        // Les directives sont automatiquement découvertes dans :
        // - tests/Fixtures/Directives/ (via le chemin configuré)
        // - vendor/*/src/Directives/ (pour les packages)

        // 5. Run migrations
        $this->runDatabaseMigrations();

        // 6. Forcer la recréation du DiscoveryService
        $this->app->forgetInstance(DirectiveDiscoveryService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->laravelBootstrapper->reset();
        parent::tearDown();
    }

    private function configureDirectivesPath(): void
    {
        $fixturesPath = __DIR__.'/Fixtures/Directives';
        $config = DirectiveConfig::default()->withDirectivesPath($fixturesPath);
        $this->app->instance(DirectiveConfig::class, $config);
        $this->app['config']->set('directive.path', $fixturesPath);
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('view.compiled', __DIR__.'/storage/framework/views');
        $app['config']->set('directive.path', __DIR__.'/Fixtures/Directives');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);
    }

    protected function getPackageProviders($app)
    {
        return [
            DirectiveServiceProvider::class,
        ];
    }

    protected function runDatabaseMigrations(): void
    {
        $migrationPath = __DIR__.'/database/migrations';

        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }

        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--force' => true,
        ])->run();
    }
}
