<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Providers;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

final class ConfigServiceProvider extends ServiceProvider
{
    /**
     * @var int Profondeur de récursion maximale pour éviter les boucles infinies
     */
    private const MAX_RECURSION_DEPTH = 20;

    /**
     * @var int Profondeur de récursion actuelle
     */
    private static int $recursionDepth = 0;

    /**
     * @var array<string, bool> Liste des chemins déjà visités (pour détection de cycles)
     */
    private static array $visitedPaths = [];

    public function register(): void
    {
        $this->app->singleton(ConfigRepository::class, function ($app) {
            $config = [];
            $basePath = $app->basePath();

            // Liste des dossiers de configuration à scanner
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

            // Enregistrer le service 'config'
            $this->app->instance('config', $repository);

            return $repository;
        });

        // Alias 'config' vers ConfigRepository
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
        // ✅ Vérifier la profondeur de récursion
        self::$recursionDepth++;
        if (self::$recursionDepth > self::MAX_RECURSION_DEPTH) {
            self::$recursionDepth--;

            return;
        }

        // ✅ Résoudre le chemin réel pour détecter les liens symboliques
        $realPath = realpath($dir);
        if ($realPath === false) {
            self::$recursionDepth--;

            return;
        }

        // ✅ Détection des cycles (dossier déjà visité)
        if (isset(self::$visitedPaths[$realPath])) {
            self::$recursionDepth--;

            return;
        }

        self::$visitedPaths[$realPath] = true;

        $files = scandir($dir);
        if ($files === false) {
            self::$recursionDepth--;

            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir.'/'.$file;

            // ✅ Récursion pour les sous-dossiers
            if (is_dir($path)) {
                $this->loadConfigFiles($path, $config);

                continue;
            }

            // ✅ Vérifier que c'est un fichier PHP
            if (! is_file($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            // ✅ Extraire le nom de la clé de configuration
            $key = pathinfo($file, PATHINFO_FILENAME);

            // ✅ Charger le fichier de configuration avec gestion d'erreur
            try {
                $content = require $path;
                if (! is_array($content)) {
                    continue;
                }
            } catch (\Throwable $e) {
                // Ignorer silencieusement les erreurs de chargement
                continue;
            }

            // ✅ Fusionner avec la config existante
            if (array_key_exists($key, $config) && is_array($config[$key])) {
                $config[$key] = array_merge($config[$key], $content);
            } else {
                $config[$key] = $content;
            }
        }

        self::$recursionDepth--;
    }
}
