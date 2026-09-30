<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Bootstrap;

use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Enums\ApplicationType;
use AndyDefer\Directive\Factories\ExternalApplicationFactory;
use AndyDefer\Directive\Factories\InternalApplicationFactory;
use AndyDefer\Directive\Helpers\EnvironmentDetector;
use AndyDefer\Directive\Providers\ConfigServiceProvider;
use AndyDefer\Directive\Providers\ViewServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

/**
 * Factory for creating and configuring the Directive application.
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
     * @var array<string, list<string>>
     */
    private array $viewNamespaces = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $databaseConfig = null;

    /**
     * @var list<string>
     */
    private array $sqliteFiles = [];

    private ?ApplicationType $forcedType = null;

    private function __construct()
    {
        $this->addProvider(ConfigServiceProvider::class);
        $this->addProvider(DirectiveServiceProvider::class);
    }

    /**
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function init(?ApplicationType $type = null, array $providers = []): self
    {
        $builder = new self;

        if ($type !== null) {
            $builder->forceType($type);
        }

        if ($providers !== []) {
            $builder->withProviders($providers);
        }

        return $builder;
    }

    /**
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function internal(array $providers = []): self
    {
        return self::init(ApplicationType::INTERNAL, $providers);
    }

    /**
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function external(array $providers = []): self
    {
        return self::init(ApplicationType::EXTERNAL, $providers);
    }

    /**
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function web(array $providers = []): self
    {
        return self::init(ApplicationType::WEB_APPLICATION, $providers);
    }

    /**
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function package(array $providers = []): self
    {
        return self::init(ApplicationType::PACKAGE, $providers);
    }

    /**
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function create(array $providers = [], ?ApplicationType $type = null): Application
    {
        return self::init($type, $providers)->build();
    }

    /**
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function createInternal(array $providers = []): Application
    {
        return self::internal($providers)->build();
    }

    /**
     * @param  array<class-string<ServiceProvider>>  $providers
     */
    public static function createExternal(array $providers = []): Application
    {
        return self::external($providers)->build();
    }

    public function forceType(ApplicationType $type): self
    {
        $this->forcedType = $type;

        return $this;
    }

    /**
     * @param  class-string<ServiceProvider>  $provider
     *
     * @throws InvalidArgumentException
     */
    public function withProvider(string $provider): self
    {
        $this->addProvider($provider);

        return $this;
    }

    /**
     * @param  array<class-string<ServiceProvider>>  $providers
     *
     * @throws InvalidArgumentException
     */
    public function withProviders(array $providers): self
    {
        foreach ($providers as $provider) {
            $this->addProvider($provider);
        }

        return $this;
    }

    public function withConfig(array $config): self
    {
        $this->config = $this->mergeConfigRecursive($this->config, $config);

        return $this;
    }

    public function withConfigValue(string $key, mixed $value): self
    {
        $this->config[$key] = $value;

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function withConfigPath(string $path, ?string $key = null): self
    {
        $realPath = $path === '' ? false : realpath($path);

        if ($realPath === false || ! is_file($realPath)) {
            throw new InvalidArgumentException(
                sprintf('Configuration file not found: %s', $path)
            );
        }

        $this->configPaths[$realPath] = $key ?? pathinfo($realPath, PATHINFO_FILENAME);

        return $this;
    }

    /**
     * @param  array<int|string, string|null>  $paths
     *
     * @throws InvalidArgumentException
     */
    public function withConfigPaths(array $paths): self
    {
        foreach ($paths as $path => $key) {
            if (is_int($path)) {
                $this->withConfigPath((string) $key);
            } else {
                $this->withConfigPath($path, $key);
            }
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function withDatabase(array $config, string $connection = 'sqlite'): self
    {
        $this->addProvider(EventServiceProvider::class);
        $this->addProvider(DatabaseServiceProvider::class);

        $this->databaseConfig = $this->mergeConfigRecursive(
            $this->databaseConfig ?? [
                'default' => $connection,
                'connections' => [],
                'migrations' => 'migrations',
            ],
            $config
        );

        return $this;
    }

    public function withSqlite(string $databaseFile, bool $foreignKeyConstraints = true): self
    {
        if ($databaseFile !== '' && $databaseFile !== ':memory:') {
            $this->sqliteFiles[] = $databaseFile;
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

    public function withMySql(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306,
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
     * @param  list<string>  $paths
     */
    public function withViews(array $paths, string $namespace = 'app'): self
    {
        $this->addProvider(ViewServiceProvider::class);

        foreach ($paths as $path) {
            $this->addViewPath($path, $namespace);
        }

        return $this;
    }

    public function withViewPath(string $path, string $namespace = 'app'): self
    {
        $this->addProvider(ViewServiceProvider::class);
        $this->addViewPath($path, $namespace);

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function build(): Application
    {
        $app = $this->createBaseApplication();

        // LoadConfiguration déclenche EventServiceProvider, qui résout
        // le binding 'files' (Filesystem). On le lie avant le bootstrap
        // pour éviter BindingResolutionException.
        if (! $app->bound('files')) {
            $app->singleton('files', static fn (): Filesystem => new Filesystem);
        }

        // Charge la configuration Laravel (fichiers config/) et crée
        // le repository 'config' avant toute autre étape.
        $app->bootstrapWith([
            LoadConfiguration::class,
        ]);

        $viewsCachePath = $this->prepareViewsCache();
        $this->prepareSqliteFiles();
        $this->applyViewConfig($app, $viewsCachePath);
        $this->loadConfigFiles($app);
        $this->applyConfig($app);
        $this->applyDatabaseConfig($app);

        foreach ($this->providers as $providerClass) {
            $app->register($providerClass);
        }

        // boot() est idempotent : il ne boote que les providers non encore bootés.
        $app->boot();

        return $app;
    }

    /**
     * @param  class-string<ServiceProvider>  $provider
     *
     * @throws InvalidArgumentException
     */
    private function addProvider(string $provider): void
    {
        if (! is_subclass_of($provider, ServiceProvider::class)) {
            throw new InvalidArgumentException(
                sprintf('Class "%s" must extend %s', $provider, ServiceProvider::class)
            );
        }

        if (! in_array($provider, $this->providers, true)) {
            $this->providers[] = $provider;
        }
    }

    private function addViewPath(string $path, string $namespace): void
    {
        if (! isset($this->viewNamespaces[$namespace])) {
            $this->viewNamespaces[$namespace] = [];
        }

        if (! in_array($path, $this->viewNamespaces[$namespace], true)) {
            $this->viewNamespaces[$namespace][] = $path;
        }
    }

    /**
     * @throws RuntimeException
     */
    private function prepareViewsCache(): ?string
    {
        if ($this->viewNamespaces === []) {
            return null;
        }

        $allPaths = array_merge(...array_values($this->viewNamespaces));

        $hasPosix = function_exists('posix_geteuid');
        $uid = $hasPosix ? posix_geteuid() : (getmyuid() ?: 0);

        $cachePath = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'directive-views-cache-'
            .$uid
            .'-'
            .substr(hash('sha256', implode('|', $allPaths)), 0, 16);

        // Un lien symbolique posé à l'avance pourrait rediriger le cache ailleurs.
        if (is_link($cachePath)) {
            throw new RuntimeException(sprintf('Unsafe view cache directory: %s', $cachePath));
        }

        if (! is_dir($cachePath) && ! @mkdir($cachePath, 0700, true) && ! is_dir($cachePath)) {
            throw new RuntimeException(
                sprintf('Unable to create view cache directory: %s', $cachePath)
            );
        }

        // Le dossier existait peut-être déjà, créé par quelqu'un d'autre.
        if ($hasPosix && fileowner($cachePath) !== $uid) {
            throw new RuntimeException(sprintf('Unsafe view cache directory: %s', $cachePath));
        }

        @chmod($cachePath, 0700);

        return $cachePath;
    }

    /**
     * @throws RuntimeException
     */
    private function prepareSqliteFiles(): void
    {
        foreach ($this->sqliteFiles as $databaseFile) {
            $directory = dirname($databaseFile);

            if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException(
                    sprintf('Unable to create SQLite directory: %s', $directory)
                );
            }

            if (! file_exists($databaseFile) && @touch($databaseFile) === false) {
                throw new RuntimeException(
                    sprintf('Unable to create SQLite database file: %s', $databaseFile)
                );
            }
        }
    }

    private function applyViewConfig(Application $app, ?string $viewsCachePath): void
    {
        if ($this->viewNamespaces === [] || $viewsCachePath === null) {
            return;
        }

        $config = $app->make('config');

        $paths = $config->get('view.paths', []);

        foreach ($this->viewNamespaces as $namespacePaths) {
            foreach ($namespacePaths as $path) {
                if (! in_array($path, $paths, true)) {
                    $paths[] = $path;
                }
            }
        }

        $config->set('view.paths', $paths);
        $config->set('view.compiled', $viewsCachePath);
        $config->set('view.cache', true);

        $namespaces = $config->get('view.namespaces', []);

        foreach ($this->viewNamespaces as $namespace => $namespacePaths) {
            if (! isset($namespaces[$namespace])) {
                $namespaces[$namespace] = [];
            }

            foreach ($namespacePaths as $path) {
                if (! in_array($path, $namespaces[$namespace], true)) {
                    $namespaces[$namespace][] = $path;
                }
            }
        }

        $config->set('view.namespaces', $namespaces);
    }

    private function createBaseApplication(): Application
    {
        return match ($this->forcedType) {
            ApplicationType::INTERNAL, ApplicationType::WEB_APPLICATION, ApplicationType::PACKAGE => InternalApplicationFactory::create(),
            ApplicationType::EXTERNAL => ExternalApplicationFactory::create(),
            null => $this->detectApplication(),
        };
    }

    private function detectApplication(): Application
    {
        if (EnvironmentDetector::isWebApplication()) {
            return InternalApplicationFactory::create();
        }

        return InternalApplicationFactory::create();
    }

    private function loadConfigFiles(Application $app): void
    {
        if ($this->configPaths === []) {
            return;
        }

        $config = $app->make('config');

        foreach ($this->configPaths as $path => $key) {
            $loaded = (static fn () => require $path)();

            if (! is_array($loaded)) {
                throw new InvalidArgumentException(
                    sprintf('Configuration file must return an array: %s', $path)
                );
            }

            $current = $config->get($key, []);

            $config->set($key, $this->mergeConfigRecursive(
                is_array($current) ? $current : [],
                $loaded
            ));
        }
    }

    private function applyConfig(Application $app): void
    {
        if ($this->config === []) {
            return;
        }

        $config = $app->make('config');

        foreach ($this->config as $key => $value) {
            $current = $config->get($key);

            if (is_array($current) && is_array($value)) {
                $config->set($key, $this->mergeConfigRecursive($current, $value));
            } else {
                $config->set($key, $value);
            }
        }
    }

    private function applyDatabaseConfig(Application $app): void
    {
        if ($this->databaseConfig === null) {
            return;
        }

        $config = $app->make('config');
        $current = $config->get('database', []);

        $config->set('database', $this->mergeConfigRecursive(
            is_array($current) ? $current : [],
            $this->databaseConfig
        ));
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function mergeConfigRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                isset($base[$key])
                && is_array($base[$key])
                && is_array($value)
                && ! array_is_list($base[$key])
                && ! array_is_list($value)
            ) {
                $base[$key] = $this->mergeConfigRecursive($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
