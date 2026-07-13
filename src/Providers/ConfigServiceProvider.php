<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Providers;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

final class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigRepository::class, function ($app) {
            $config = [];
            $basePath = $app->basePath();

            // ✅ Liste des dossiers de configuration à scanner
            $configDirs = [
                $basePath.'/config',
                $basePath.'/configs',
                $basePath.'/src/config',
                $basePath.'/src/configs',
                $basePath.'/resources/config',
            ];

            foreach ($configDirs as $dir) {
                if (is_dir($dir)) {
                    $this->loadConfigFiles($dir, $config);
                }
            }

            $repository = new Repository($config);

            // ✅ Enregistrer le service 'config'
            $this->app->instance('config', $repository);

            return $repository;
        });

        // ✅ Alias 'config' vers ConfigRepository
        $this->app->alias(ConfigRepository::class, 'config');
    }

    /**
     * Charge tous les fichiers de configuration d'un dossier.
     *
     * @param  string  $dir  Le dossier à scanner
     * @param  array<string, mixed>  $config  Le tableau de configuration (passé par référence)
     */
    private function loadConfigFiles(string $dir, array &$config): void
    {
        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir.'/'.$file;

            if (is_dir($path)) {
                // ✅ Récursion pour les sous-dossiers
                $this->loadConfigFiles($path, $config);

                continue;
            }

            // ✅ Vérifier que c'est un fichier PHP
            if (! is_file($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            // ✅ Extraire le nom de la clé de configuration
            $key = pathinfo($file, PATHINFO_FILENAME);

            // ✅ Charger le fichier de configuration
            $content = require $path;
            if (! is_array($content)) {
                continue;
            }

            // ✅ Fusionner avec la config existante
            if (array_key_exists($key, $config) && is_array($config[$key])) {
                $config[$key] = array_merge($config[$key], $content);
            } else {
                $config[$key] = $content;
            }
        }
    }
}
