<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Providers;

use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\DynamicComponent;
use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewServiceProvider as BaseViewServiceProvider;

/**
 * Override du ViewServiceProvider pour forcer les chemins de vues
 * dans le contexte CLI.
 *
 * Ce provider est utilisé par ApplicationBuilder pour garantir
 * que les vues fonctionnent correctement en environnement CLI.
 */
final class ViewServiceProvider extends BaseViewServiceProvider
{
    /**
     * Register the view finder implementation.
     *
     * On override la méthode pour s'assurer que les chemins sont bien définis.
     */
    public function registerViewFinder(): void
    {
        $this->app->bind('view.finder', function ($app) {
            // ✅ Récupérer les chemins de la config, ou utiliser un fallback
            $paths = $app['config']->get('view.paths', []);

            // ✅ Si aucun chemin n'est défini, utiliser un chemin par défaut
            if (empty($paths)) {
                $paths = [base_path('resources/views')];
                $app['config']->set('view.paths', $paths);
            }

            return new FileViewFinder(
                $app['files'],
                $paths,
                $app['config']->get('view.extensions', ['blade.php', 'php', 'css', 'html'])
            );
        });
    }

    /**
     * Register the Blade compiler implementation.
     *
     * On override pour s'assurer que le cache est bien défini.
     */
    public function registerBladeCompiler(): void
    {
        $this->app->singleton('blade.compiler', function ($app) {
            // ✅ S'assurer que le chemin de cache existe
            $cachePath = $app['config']->get('view.compiled', sys_get_temp_dir().'/directive-views-cache');

            if (! is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }

            return tap(new BladeCompiler(
                $app['files'],
                $cachePath,
                $app['config']->get('view.relative_hash', false) ? $app->basePath() : '',
                $app['config']->get('view.cache', true),
                $app['config']->get('view.compiled_extension', 'php'),
                $app['config']->get('view.check_cache_timestamps', true),
            ), function ($blade) {
                $blade->component('dynamic-component', DynamicComponent::class);
            });
        });
    }
}
