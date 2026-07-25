<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Bootstrap;

use AndyDefer\Directive\Enums\ApplicationType;
use AndyDefer\Directive\Factories\ExternalApplicationFactory;
use AndyDefer\Directive\Factories\InternalApplicationFactory;
use AndyDefer\Directive\Helpers\EnvironmentDetector;
use AndyDefer\Directive\Providers\ConfigServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\ViewServiceProvider;

/**
 * Factory for creating and configuring the Directive application.
 *
 * This class provides both a fluent builder interface and a simple
 * factory method for creating Laravel applications with custom
 * service providers and configuration.
 *
 * @example
 * // Internal Laravel application
 * $app = ApplicationBuilder::internal([
 *     DirectiveServiceProvider::class
 * ])->build();
 *
 * // External standalone application with config
 * $app = ApplicationBuilder::external([
 *     DirectiveServiceProvider::class
 * ])->withConfig(['debug' => true])->build();
 *
 * // With automatic detection
 * $app = ApplicationBuilder::create([DirectiveServiceProvider::class]);
 */
final class ApplicationBuilder
{
    /**
     * @var array<class-string<ServiceProvider>>
     */
    private array $providers = [];

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * @var array<string, string>
     */
    private array $configPaths = [];

    /**
     * @var array<string, string> View paths
     */
    private array $viewPaths = [];

    private ?ApplicationType $forcedType = null;

    private bool $viewsLoaded = false;

    /**
     * Private constructor to enforce factory pattern.
     */
    private function __construct()
    {
        // ✅ ConfigServiceProvider chargé automatiquement par défaut
        $this->providers[] = ConfigServiceProvider::class;
    }

    /**
     * Create a new application builder instance.
     *
     * @param  ApplicationType|null  $type  Force a specific application type
     * @param  array<class-string<ServiceProvider>>  $providers  Service providers to register
     */
    public static function init(?ApplicationType $type = null, array $providers = []): self
    {
        $builder = new self;

        if ($type !== null) {
            $builder->forceType($type);
        }

        if (! empty($providers)) {
            $builder->withProviders($providers);
        }

        return $builder;
    }

    /**
     * Create a builder for an internal (Laravel) application.
     *
     * This forces the builder to use the InternalApplicationFactory
     * regardless of environment detection.
     *
     * @param  array<class-string<ServiceProvider>>  $providers  Service providers to register
     */
    public static function internal(array $providers = []): self
    {
        return self::init(ApplicationType::INTERNAL, $providers);
    }

    /**
     * Create a builder for an external (standalone) application.
     *
     * This forces the builder to use the ExternalApplicationFactory
     * regardless of environment detection.
     *
     * @param  array<class-string<ServiceProvider>>  $providers  Service providers to register
     */
    public static function external(array $providers = []): self
    {
        return self::init(ApplicationType::EXTERNAL, $providers);
    }

    /**
     * Create a builder for a web application context.
     *
     * Forces the builder to use the InternalApplicationFactory
     * with web application detection.
     *
     * @param  array<class-string<ServiceProvider>>  $providers  Service providers to register
     */
    public static function web(array $providers = []): self
    {
        return self::init(ApplicationType::WEB_APPLICATION, $providers);
    }

    /**
     * Create a builder for a package/library context.
     *
     * Forces the builder to use the ExternalApplicationFactory
     * with package detection.
     *
     * @param  array<class-string<ServiceProvider>>  $providers  Service providers to register
     */
    public static function package(array $providers = []): self
    {
        return self::init(ApplicationType::PACKAGE, $providers);
    }

    /**
     * Create application with providers (simple approach).
     *
     * @param  array<class-string<ServiceProvider>>  $providers
     * @param  ApplicationType|null  $type  Force a specific application type
     */
    public static function create(array $providers = [], ?ApplicationType $type = null): Application
    {
        return self::init($type, $providers)
            ->build();
    }

    /**
     * Create an internal (Laravel) application with providers.
     *
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function createInternal(array $providers = []): Application
    {
        return self::internal($providers)
            ->build();
    }

    /**
     * Create an external (standalone) application with providers.
     *
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function createExternal(array $providers = []): Application
    {
        return self::external($providers)
            ->build();
    }

    /**
     * Force the application to be built with a specific type.
     *
     * @param  ApplicationType  $type  The application type to force
     */
    public function forceType(ApplicationType $type): self
    {
        $this->forcedType = $type;

        return $this;
    }

    /**
     * Add a service provider.
     *
     * @param  class-string<ServiceProvider>  $provider
     */
    public function withProvider(string $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Add multiple service providers.
     *
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public function withProviders(array $providers): self
    {
        $this->providers = array_merge($this->providers, $providers);

        return $this;
    }

    /**
     * Set configuration.
     */
    public function withConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Set a single configuration value.
     */
    public function withConfigValue(string $key, mixed $value): self
    {
        $this->config[$key] = $value;

        return $this;
    }

    /**
     * Add a configuration file path.
     *
     * The configuration file should return an array of configuration values.
     *
     * @param  string  $path  Absolute path to the configuration file
     * @param  string|null  $key  Optional key to nest the config under
     */
    public function withConfigPath(string $path, ?string $key = null): self
    {
        $this->configPaths[$path] = $key ?? pathinfo($path, PATHINFO_FILENAME);

        return $this;
    }

    /**
     * Add multiple configuration file paths.
     *
     * @param  array<string, string|null>  $paths  Array of paths with optional keys
     *
     * @example
     *   withConfigPaths([
     *       '/path/to/directive.php',              // key = 'directive'
     *       '/path/to/nemesis.php' => 'nemesis',   // explicit key
     *   ])
     */
    public function withConfigPaths(array $paths): self
    {
        foreach ($paths as $path => $key) {
            if (is_int($path)) {
                // No key provided, use filename
                $this->withConfigPath($key);
            } else {
                // Key provided
                $this->withConfigPath($path, $key);
            }
        }

        return $this;
    }

    /**
     * Configure the database connection.
     *
     * @param  array<string, mixed>  $config  Database configuration
     * @param  string  $connection  The connection name (default: 'sqlite')
     *
     * @example
     * // SQLite
     * $builder->withDatabase([
     *     'default' => 'sqlite',
     *     'connections' => [
     *         'sqlite' => [
     *             'driver' => 'sqlite',
     *             'database' => '/path/to/database.sqlite',
     *         ],
     *     ],
     * ]);
     *
     * // MySQL
     * $builder->withDatabase([
     *     'default' => 'mysql',
     *     'connections' => [
     *         'mysql' => [
     *             'driver' => 'mysql',
     *             'host' => 'localhost',
     *             'database' => 'my_database',
     *             'username' => 'root',
     *             'password' => 'secret',
     *         ],
     *     ],
     * ]);
     */
    public function withDatabase(array $config, string $connection = 'sqlite'): self
    {
        // ✅ Ajouter les providers de base de données
        $this->withProviders([
            EventServiceProvider::class,
            DatabaseServiceProvider::class,
        ]);

        // ✅ Ajouter la configuration de la base de données
        $this->config['database'] = array_merge(
            [
                'default' => $connection,
                'connections' => [],
                'migrations' => 'migrations',
            ],
            $config
        );

        return $this;
    }

    /**
     * Configure SQLite database.
     *
     * @param  string  $databaseFile  Path to the SQLite database file
     * @param  bool  $foreignKeyConstraints  Enable foreign key constraints
     *
     * @example
     * $builder->withSqlite('/path/to/database.sqlite');
     */
    public function withSqlite(string $databaseFile, bool $foreignKeyConstraints = true): self
    {
        // ✅ Créer le dossier si nécessaire
        $dir = dirname($databaseFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // ✅ Créer le fichier s'il n'existe pas
        if (! file_exists($databaseFile)) {
            touch($databaseFile);
        }

        return $this->withDatabase([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $databaseFile,
                    'prefix' => '',
                    'foreign_key_constraints' => $foreignKeyConstraints,
                ],
            ],
        ]);
    }

    /**
     * Configure MySQL database.
     *
     * @param  string  $host  Database host
     * @param  string  $database  Database name
     * @param  string  $username  Database username
     * @param  string  $password  Database password
     * @param  int  $port  Database port
     *
     * @example
     * $builder->withMySql('localhost', 'my_database', 'root', 'secret');
     */
    public function withMySql(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306
    ): self {
        return $this->withDatabase([
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $username,
                    'password' => $password,
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => null,
                ],
            ],
        ]);
    }

    /**
     * Configure view paths for CLI context.
     *
     * This is useful when using MailChannel or other view-dependent
     * services in CLI directives.
     *
     * @param  array<string>  $paths  Array of view paths
     * @param  string  $namespace  The namespace for the views (default: 'app')
     *
     * @example
     * $builder->withViews([resource_path('views')]);
     * $builder->withViews([resource_path('views'), resource_path('emails')]);
     */
    public function withViews(array $paths, string $namespace = 'app'): self
    {
        $this->viewPaths = $paths;

        // ✅ Ajouter le ViewServiceProvider
        $this->withProvider(ViewServiceProvider::class);

        // ✅ Configurer les chemins de vues
        $this->config['view'] = array_merge(
            $this->config['view'] ?? [],
            [
                'paths' => $paths,
                'namespaces' => [
                    $namespace => $paths,
                ],
            ]
        );

        $this->viewsLoaded = true;

        return $this;
    }

    /**
     * Add a single view path.
     *
     * @param  string  $path  View path
     * @param  string  $namespace  The namespace for the views (default: 'app')
     */
    public function withViewPath(string $path, string $namespace = 'app'): self
    {
        $this->viewPaths[] = $path;

        // ✅ Ajouter le ViewServiceProvider si pas encore ajouté
        if (! $this->viewsLoaded) {
            $this->withProvider(ViewServiceProvider::class);
            $this->viewsLoaded = true;
        }

        // ✅ Configurer les chemins de vues
        $this->config['view'] = array_merge(
            $this->config['view'] ?? [],
            [
                'paths' => $this->viewPaths,
                'namespaces' => [
                    $namespace => $this->viewPaths,
                ],
            ]
        );

        return $this;
    }

    /**
     * Build the application.
     */
    public function build(): Application
    {
        $app = $this->createBaseApplication();

        $this->loadConfigFiles($app);
        $this->applyConfig($app);
        $this->registerProviders($app);
        $this->bootProviders($app);

        // ✅ Forcer le chargement des vues après le boot
        if ($this->viewsLoaded) {
            $this->ensureViewsLoaded($app);
        }

        return $app;
    }

    /**
     * Ensure views are loaded correctly.
     */
    private function ensureViewsLoaded(Application $app): void
    {
        try {
            // ✅ Forcer la configuration des chemins de vues
            if (! empty($this->viewPaths)) {
                $app->make('config')->set('view.paths', $this->viewPaths);
            }

            // ✅ Forcer l'initialisation du view finder
            $app->make('view.finder');
        } catch (\Exception $e) {
            // Ignorer les erreurs de vue en CLI
        }
    }

    /**
     * Create base application.
     */
    private function createBaseApplication(): Application
    {
        // ✅ Use forced type if specified
        if ($this->forcedType !== null) {
            return match ($this->forcedType) {
                ApplicationType::INTERNAL => InternalApplicationFactory::create(),
                ApplicationType::EXTERNAL => ExternalApplicationFactory::create(),
                ApplicationType::WEB_APPLICATION => InternalApplicationFactory::create(),
                ApplicationType::PACKAGE => ExternalApplicationFactory::create(),
                default => $this->detectApplication(),
            };
        }

        return $this->detectApplication();
    }

    /**
     * Detect the application type from the environment.
     */
    private function detectApplication(): Application
    {
        if (EnvironmentDetector::isWebApplication()) {
            return InternalApplicationFactory::create();
        }

        if (EnvironmentDetector::isPackage()) {
            return ExternalApplicationFactory::create();
        }

        // Default to External (standalone)
        return ExternalApplicationFactory::create();
    }

    /**
     * Load configuration from files.
     */
    private function loadConfigFiles(Application $app): void
    {
        foreach ($this->configPaths as $path => $key) {
            if (! file_exists($path)) {
                throw new \InvalidArgumentException(
                    sprintf('Configuration file not found: %s', $path)
                );
            }

            $config = require $path;

            if (! is_array($config)) {
                throw new \InvalidArgumentException(
                    sprintf('Configuration file must return an array: %s', $path)
                );
            }

            // Merge with existing config
            if (method_exists($app, 'config')) {
                $current = $app->config->get($key, []);
                $app->config->set($key, array_merge($current, $config));
            }
        }
    }

    /**
     * Apply configuration to application.
     */
    private function applyConfig(Application $app): void
    {
        foreach ($this->config as $key => $value) {
            if (method_exists($app, 'config')) {
                $current = $app->config->get($key, []);

                if (is_array($current) && is_array($value)) {
                    $app->config->set($key, array_merge($current, $value));
                } else {
                    $app->config->set($key, $value);
                }
            }
        }
    }

    /**
     * Register all providers.
     */
    private function registerProviders(Application $app): void
    {
        foreach ($this->providers as $providerClass) {
            if (! is_subclass_of($providerClass, ServiceProvider::class)) {
                throw new \InvalidArgumentException(
                    sprintf('Class "%s" must extend %s', $providerClass, ServiceProvider::class)
                );
            }

            /** @var ServiceProvider $provider */
            $provider = new $providerClass($app);
            $provider->register();
        }
    }

    /**
     * Boot all providers.
     */
    private function bootProviders(Application $app): void
    {
        foreach ($this->providers as $providerClass) {
            /** @var ServiceProvider $provider */
            $provider = new $providerClass($app);

            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
    }
}
