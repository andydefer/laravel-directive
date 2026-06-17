<?php

// tests/Unit/Cli/CliRunnerTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Feature\Cli;

use AndyDefer\Directive\Cli\CliRunner;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class CliRunnerTest extends IntegrationTestCase
{
    private static ?string $appRoot = null;

    private string $originalCwd;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$appRoot === null) {
            self::$appRoot = sys_get_temp_dir().'/laravel_app_'.uniqid();
            self::createRealisticLaravelStructure(self::$appRoot);
            self::includeDirectiveFiles(self::$appRoot);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$appRoot !== null && is_dir(self::$appRoot)) {
            self::removeDirectory(self::$appRoot);
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = getcwd();
        chdir(self::$appRoot);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        parent::tearDown();
    }

    private function runDirective(CliRunner $runner, array $argv): array
    {
        ob_start();
        $exit_code = $runner->run($argv);
        $output = ob_get_clean();

        return [
            'exit_code' => $exit_code,
            'output' => $output,
        ];
    }

    private function runDirectiveSilent(CliRunner $runner, array $argv): int
    {
        ob_start();
        $exit_code = $runner->run($argv);
        ob_end_clean();

        return $exit_code;
    }

    private static function createRealisticLaravelStructure(string $appRoot): void
    {
        $directories = [
            '/app/Directives',
            '/bootstrap',
            '/config',
        ];

        foreach ($directories as $dir) {
            if (! is_dir($appRoot.$dir)) {
                mkdir($appRoot.$dir, 0777, true);
            }
        }

        self::createBootstrapApp($appRoot);
        self::createConfigApp($appRoot);
        self::createAppDirectives($appRoot);
    }

    private static function createBootstrapApp(string $appRoot): void
    {
        $content = <<<'PHP'
<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Facade;

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Kernel::class,
    Illuminate\Foundation\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    Illuminate\Foundation\Console\Kernel::class
);

$app->singleton(
    ExceptionHandler::class,
    Handler::class
);

Facade::setFacadeApplication($app);

return $app;
PHP;

        file_put_contents($appRoot.'/bootstrap/app.php', $content);
    }

    private static function createConfigApp(string $appRoot): void
    {
        $content = <<<'PHP'
<?php

return [
    'name' => 'Laravel Test Application',
    'env' => 'testing',
    'debug' => true,
    'url' => 'http://localhost',
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => 'base64:' . base64_encode(random_bytes(32)),
    'cipher' => 'AES-256-CBC',
    'providers' => [
        AndyDefer\Directive\DirectiveServiceProvider::class,
    ],
];
PHP;

        file_put_contents($appRoot.'/config/app.php', $content);
    }

    private static function createAppDirectives(string $appRoot): void
    {
        $content1 = <<<'PHP'
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class UserCreateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-create {name} {email} {--admin}';
    }

    public function getDescription(): string
    {
        return 'Create a new user';
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $isAdmin = $this->option('admin');
        $message = "User {$name} ({$email}) created";
        if ($isAdmin) {
            $message .= " as admin";
        }
        $this->info($message);
        return ExitCode::SUCCESS;
    }
}
PHP;
        file_put_contents($appRoot.'/app/Directives/UserCreateDirective.php', $content1);

        $content2 = <<<'PHP'
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class CacheClearDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'cache-clear {--force}';
    }

    public function getDescription(): string
    {
        return 'Clear application cache';
    }

    public function execute(): ExitCode
    {
        $force = $this->option('force');
        $message = "Cache cleared";
        if ($force) {
            $message .= " (forced)";
        }
        $this->info($message);
        return ExitCode::SUCCESS;
    }
}
PHP;
        file_put_contents($appRoot.'/app/Directives/CacheClearDirective.php', $content2);
    }

    private static function includeDirectiveFiles(string $appRoot): void
    {
        $files = [
            $appRoot.'/app/Directives/UserCreateDirective.php',
            $appRoot.'/app/Directives/CacheClearDirective.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    private static function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    // ==================== Tests ====================

    public function test_runner_finds_app_directive(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'user-create', 'John Doe', 'john@example.com']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('User John Doe (john@example.com) created', $result['output']);
    }

    public function test_runner_finds_app_directive_with_option(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'user-create', 'Jane Doe', 'jane@example.com', '--admin']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('User Jane Doe (jane@example.com) created as admin', $result['output']);
    }

    public function test_runner_finds_another_app_directive(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'cache-clear']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Cache cleared', $result['output']);
    }

    public function test_runner_handles_force_option(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'cache-clear', '--force']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Cache cleared (forced)', $result['output']);
    }

    public function test_runner_displays_all_directives(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', '--list']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('user-create', $result['output']);
        $this->assertStringContainsString('cache-clear', $result['output']);
    }

    public function test_runner_returns_not_found_for_unknown(): void
    {
        $runner = new CliRunner($this->app);

        $exitCode = $this->runDirectiveSilent($runner, ['directive', 'unknown-command']);

        $this->assertSame(3, $exitCode);
    }

    public function test_runner_shows_help(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', '--help']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Directive System', $result['output']);
    }

    public function test_runner_shows_version(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', '--version']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Laravel Directive', $result['output']);
    }

    public function test_runner_finds_directive_with_alias(): void
    {
        $content = <<<'PHP'
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class AliasTestDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'original-name';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        $aliases->add('alias');
        return $aliases;
    }

    public function getDescription(): string
    {
        return 'Directive with alias';
    }

    public function execute(): ExitCode
    {
        $this->info('Alias works!');
        return ExitCode::SUCCESS;
    }
}
PHP;

        file_put_contents(self::$appRoot.'/app/Directives/AliasTestDirective.php', $content);
        require_once self::$appRoot.'/app/Directives/AliasTestDirective.php';

        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'alias']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Alias works!', $result['output']);
    }
}
