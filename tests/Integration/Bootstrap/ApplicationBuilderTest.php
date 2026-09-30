<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Bootstrap;

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Providers\ConfigServiceProvider;
use AndyDefer\Directive\Providers\ViewServiceProvider;
use AndyDefer\Directive\Tests\Fixtures\Providers\NoopServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationBuilderTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    /**
     * @var list<string>
     */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }

        $this->tempFiles = [];
        $this->tempDirs = [];

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    private function makeTempDir(string $prefix = 'directive_builder_'): string
    {
        $dir = sys_get_temp_dir().'/'.$prefix.uniqid('', true);

        if (! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException(sprintf('Unable to create temp dir: %s', $dir));
        }

        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function makeTempConfigFile(string $name, array $content): string
    {
        $dir = $this->makeTempDir('directive_config_');
        $path = $dir.'/'.$name;

        file_put_contents($path, '<?php return '.var_export($content, true).';');

        $this->tempFiles[] = $path;

        return $path;
    }

    private function assertApplication(mixed $app): Application
    {
        $this->assertInstanceOf(Application::class, $app);

        return $app;
    }

    // ==================== PROVIDERS ====================

    public function test_builder_registers_directive_service_provider_by_default(): void
    {
        $app = $this->assertApplication(ApplicationBuilder::internal()->build());

        $this->assertTrue($app->providerIsLoaded(DirectiveServiceProvider::class));
    }

    public function test_builder_registers_config_service_provider_by_default(): void
    {
        $app = $this->assertApplication(ApplicationBuilder::internal()->build());

        $this->assertTrue($app->providerIsLoaded(ConfigServiceProvider::class));
    }

    public function test_builder_deduplicates_providers(): void
    {
        $builder = ApplicationBuilder::internal()
            ->withProviders([
                DirectiveServiceProvider::class,
                NoopServiceProvider::class,
                NoopServiceProvider::class,
            ])
            ->withProvider(NoopServiceProvider::class)
            ->withDatabase([])
            ->withDatabase([]);

        $property = new \ReflectionProperty(ApplicationBuilder::class, 'providers');
        $providers = $property->getValue($builder);

        $this->assertSame($providers, array_values(array_unique($providers)));
        $this->assertCount(1, array_keys($providers, NoopServiceProvider::class, true));
        $this->assertCount(1, array_keys($providers, DirectiveServiceProvider::class, true));
        $this->assertCount(1, array_keys($providers, DatabaseServiceProvider::class, true));
    }

    public function test_builder_rejects_invalid_provider_immediately(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApplicationBuilder::internal()->withProvider(\stdClass::class);
    }

    public function test_builder_registers_database_providers_with_sqlite(): void
    {
        $file = $this->makeTempDir('directive_sqlite_').'/db.sqlite';

        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withSqlite($file)
                ->build()
        );

        $this->assertTrue($app->providerIsLoaded(EventServiceProvider::class));
        $this->assertTrue($app->providerIsLoaded(DatabaseServiceProvider::class));
    }

    public function test_builder_registers_view_service_provider_when_views_configured(): void
    {
        $dir = $this->makeTempDir('directive_views_');

        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withViews([$dir])
                ->build()
        );

        $this->assertTrue($app->providerIsLoaded(ViewServiceProvider::class));
    }

    // ==================== CONFIG ====================

    public function test_builder_applies_config_values(): void
    {
        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withConfig([
                    'app.name' => 'Test CLI',
                    'app.debug' => true,
                ])
                ->build()
        );

        $this->assertSame('Test CLI', $app->make('config')->get('app.name'));
        $this->assertTrue($app->make('config')->get('app.debug'));
    }

    public function test_builder_applies_single_config_value(): void
    {
        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withConfigValue('custom.key', 'value')
                ->build()
        );

        $this->assertSame('value', $app->make('config')->get('custom.key'));
    }

    public function test_builder_merges_config_recursively(): void
    {
        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withConfig([
                    'app' => [
                        'name' => 'CLI',
                        'debug' => false,
                    ],
                ])
                ->withConfig([
                    'app' => [
                        'debug' => true,
                        'env' => 'testing',
                    ],
                ])
                ->build()
        );

        $config = $app->make('config');

        $this->assertSame('CLI', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
        $this->assertSame('testing', $config->get('app.env'));
    }

    public function test_builder_replaces_lists_instead_of_merging_by_index(): void
    {
        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withConfig(['items' => ['a', 'b']])
                ->withConfig(['items' => ['c']])
                ->build()
        );

        $this->assertSame(['c'], $app->make('config')->get('items'));
    }

    public function test_builder_loads_config_file(): void
    {
        $file = $this->makeTempConfigFile('custom.php', [
            'key' => 'from-file',
        ]);

        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withConfigPath($file, 'custom')
                ->build()
        );

        $this->assertSame('from-file', $app->make('config')->get('custom.key'));
    }

    public function test_builder_uses_filename_as_config_key(): void
    {
        $file = $this->makeTempConfigFile('myconfig.php', [
            'key' => 'value',
        ]);

        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withConfigPath($file)
                ->build()
        );

        $this->assertSame('value', $app->make('config')->get('myconfig.key'));
    }

    public function test_builder_rejects_missing_config_file(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApplicationBuilder::internal()->withConfigPath('/nonexistent/'.uniqid().'.php');
    }

    public function test_builder_rejects_empty_config_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApplicationBuilder::internal()->withConfigPath('');
    }

    public function test_builder_rejects_directory_as_config_path(): void
    {
        $dir = $this->makeTempDir('directive_dir_');

        $this->expectException(InvalidArgumentException::class);

        ApplicationBuilder::internal()->withConfigPath($dir);
    }

    // ==================== DATABASE ====================

    public function test_sqlite_creates_database_file(): void
    {
        $dir = $this->makeTempDir('directive_sqlite_new_');
        $file = $dir.'/database.sqlite';

        $this->assertFileDoesNotExist($file);

        ApplicationBuilder::internal()
            ->withSqlite($file)
            ->build();

        $this->assertFileExists($file);
    }

    public function test_sqlite_creates_missing_directory(): void
    {
        $baseDir = $this->makeTempDir('directive_sqlite_nested_');
        $file = $baseDir.'/nested/deep/database.sqlite';

        $this->assertDirectoryDoesNotExist($baseDir.'/nested');

        ApplicationBuilder::internal()
            ->withSqlite($file)
            ->build();

        $this->assertDirectoryExists($baseDir.'/nested/deep');
        $this->assertFileExists($file);
    }

    public function test_sqlite_memory_does_not_create_any_file(): void
    {
        ApplicationBuilder::internal()
            ->withSqlite(':memory:')
            ->build();

        $this->assertFileDoesNotExist(getcwd().'/:memory:');
    }

    public function test_sqlite_empty_string_does_not_create_any_file(): void
    {
        $before = scandir(getcwd());

        ApplicationBuilder::internal()
            ->withSqlite('')
            ->build();

        $this->assertSame($before, scandir(getcwd()));
    }

    public function test_sqlite_config_is_applied(): void
    {
        $dir = $this->makeTempDir('directive_sqlite_cfg_');
        $file = $dir.'/database.sqlite';

        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withSqlite($file, true)
                ->build()
        );

        $config = $app->make('config');

        $this->assertSame('sqlite', $config->get('database.default'));
        $this->assertSame('sqlite', $config->get('database.connections.sqlite.driver'));
        $this->assertSame($file, $config->get('database.connections.sqlite.database'));
        $this->assertTrue($config->get('database.connections.sqlite.foreign_key_constraints'));
    }

    public function test_multiple_database_calls_accumulate_connections(): void
    {
        $dir = $this->makeTempDir('directive_sqlite_multi_');
        $file = $dir.'/db.sqlite';

        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withSqlite($file)
                ->withMySql('localhost', 'app', 'root', 'secret')
                ->build()
        );

        $config = $app->make('config');

        $this->assertSame($file, $config->get('database.connections.sqlite.database'));
        $this->assertSame('mysql', $config->get('database.connections.mysql.driver'));
        $this->assertSame('localhost', $config->get('database.connections.mysql.host'));
        $this->assertSame('app', $config->get('database.connections.mysql.database'));
    }

    public function test_database_requires_writable_directory(): void
    {
        $this->expectException(RuntimeException::class);

        ApplicationBuilder::internal()
            ->withSqlite('/proc/definitely-not-writable/'.uniqid().'.sqlite')
            ->build();
    }

    // ==================== VIEWS ====================

    public function test_views_paths_are_appended_to_config(): void
    {
        $dirA = $this->makeTempDir('directive_views_a_');
        $dirB = $this->makeTempDir('directive_views_b_');

        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withViews([$dirA, $dirB])
                ->build()
        );

        $paths = $app->make('config')->get('view.paths');

        $this->assertContains($dirA, $paths);
        $this->assertContains($dirB, $paths);
    }

    public function test_view_namespaces_are_recorded(): void
    {
        $dir = $this->makeTempDir('directive_views_ns_');

        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withViewPath($dir, 'admin')
                ->build()
        );

        $namespaces = $app->make('config')->get('view.namespaces');

        $this->assertArrayHasKey('admin', $namespaces);
        $this->assertContains($dir, $namespaces['admin']);
    }

    public function test_multiple_namespaces_are_preserved(): void
    {
        $dirA = $this->makeTempDir('directive_views_ns_a_');
        $dirB = $this->makeTempDir('directive_views_ns_b_');

        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withViewPath($dirA, 'admin')
                ->withViewPath($dirB, 'front')
                ->build()
        );

        $namespaces = $app->make('config')->get('view.namespaces');

        $this->assertContains($dirA, $namespaces['admin']);
        $this->assertContains($dirB, $namespaces['front']);
    }

    public function test_views_cache_directory_is_created_with_restricted_permissions(): void
    {
        $dir = $this->makeTempDir('directive_views_cache_');

        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withViews([$dir])
                ->build()
        );

        $cachePath = $app->make('config')->get('view.compiled');

        $this->assertNotNull($cachePath);
        $this->assertDirectoryExists($cachePath);

        if (function_exists('posix_geteuid') && DIRECTORY_SEPARATOR === '/') {
            $perms = fileperms($cachePath) & 0777;
            $this->assertSame(0700, $perms);
        }
    }

    public function test_views_cache_path_differs_between_different_path_sets(): void
    {
        $dirA = $this->makeTempDir('directive_views_p1_');
        $dirB = $this->makeTempDir('directive_views_p2_');

        $appA = $this->assertApplication(
            ApplicationBuilder::internal()->withViews([$dirA])->build()
        );
        $appB = $this->assertApplication(
            ApplicationBuilder::internal()->withViews([$dirB])->build()
        );

        $this->assertNotSame(
            $appA->make('config')->get('view.compiled'),
            $appB->make('config')->get('view.compiled')
        );
    }

    // ==================== FACTORY METHODS ====================

    public function test_internal_factory_creates_application(): void
    {
        $app = ApplicationBuilder::createInternal();

        $this->assertApplication($app);
    }

    // ==================== LIFECYCLE ====================

    public function test_build_twice_does_not_throw(): void
    {
        $builder = ApplicationBuilder::internal()
            ->withConfig(['app.name' => 'CLI']);

        $appA = $this->assertApplication($builder->build());
        $appB = $this->assertApplication($builder->build());

        $this->assertNotSame($appA, $appB);
        $this->assertSame('CLI', $appB->make('config')->get('app.name'));
    }

    public function test_build_twice_does_not_duplicate_sqlite_file_creation(): void
    {
        $dir = $this->makeTempDir('directive_sqlite_twice_');
        $file = $dir.'/db.sqlite';

        $builder = ApplicationBuilder::internal()->withSqlite($file);

        $builder->build();
        $builder->build();

        $this->assertFileExists($file);
    }

    public function test_config_is_available_after_build(): void
    {
        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withConfig(['app.name' => 'CLI'])
                ->build()
        );

        $this->assertTrue($app->bound('config'));
        $this->assertSame('CLI', $app->make('config')->get('app.name'));
    }

    public function test_application_is_booted_after_build(): void
    {
        $app = $this->assertApplication(ApplicationBuilder::internal()->build());

        $this->assertTrue($app->isBooted());
    }

    // ==================== PROVIDER ORDERING ====================

    public function test_default_providers_are_registered_before_custom_ones(): void
    {
        $app = $this->assertApplication(
            ApplicationBuilder::internal()
                ->withProvider(NoopServiceProvider::class)
                ->build()
        );

        $loaded = array_keys($app->getLoadedProviders());

        $configIndex = array_search(ConfigServiceProvider::class, $loaded, true);
        $customIndex = array_search(NoopServiceProvider::class, $loaded, true);

        $this->assertNotFalse($configIndex);
        $this->assertNotFalse($customIndex);
        $this->assertLessThan($customIndex, $configIndex);
    }
}
