<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Configs\EnvDirectiveConfig;
use AndyDefer\Directive\Configs\EnvDirectiveNamingConfig;
use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
use AndyDefer\Directive\Contexts\DirectiveDiscoveryContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Factories\ContainerDirectiveFactory;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use InvalidArgumentException;

/**
 * Trait providing testing utilities for directives.
 *
 * This trait allows you to test directives in an isolated environment without
 * needing to create real files or depend on the filesystem. It provides methods
 * to register directives, run them, and make assertions on their output.
 *
 * @example
 * class MyDirectiveTest extends TestCase
 * {
 *     use InteractsWithDirectives;
 *
 *     protected function setUp(): void
 *     {
 *         parent::setUp();
 *         $this->initDirectiveTesting();
 *     }
 *
 *     public function test_directive_executes_successfully(): void
 *     {
 *         $directive = new MyDirective($this->interaction);
 *         $this->registerDirective($directive);
 *
 *         $response = $this->runDirective(MyDirective::class, ['arg1', '--option']);
 *         $response->assertSuccess();
 *     }
 * }
 *
 * @author Andy Defer
 */
trait InteractsWithDirectives
{
    private Container $directiveContainer;

    private DirectiveKernel $directiveKernel;

    private TestDirectiveRegistry $directiveRegistry;

    private DirectiveInteractionService $interaction;

    private bool $directiveTestingInitialized = false;

    private string $directiveTempDir;

    private string $originalCwd;

    private ?Application $laravelApp = null;

    private bool $bootLaravelEnabled = false;

    /**
     * Initializes the testing environment for directives.
     *
     * Creates a temporary directory, sets up the service container, and optionally
     * bootstraps a minimal Laravel application.
     *
     * @param  bool  $bootLaravel  Whether to bootstrap Laravel for tests that need it
     */
    protected function initDirectiveTesting(bool $bootLaravel = false): void
    {
        if ($this->directiveTestingInitialized) {
            return;
        }

        $this->bootLaravelEnabled = $bootLaravel;
        $this->directiveTempDir = sys_get_temp_dir() . '/directive_test_' . uniqid();
        mkdir($this->directiveTempDir, 0777, true);

        $this->originalCwd = getcwd();
        chdir($this->directiveTempDir);

        if ($bootLaravel) {
            $this->createLaravelStructure();
            $this->laravelApp = $this->createApplication();
        }

        $this->directiveContainer = new Container;

        $this->directiveContainer->singleton(RenderDispatcher::class, function () {
            return new RenderDispatcher;
        });
        $this->directiveContainer->singleton(InputDispatcher::class, function () {
            return new InputDispatcher;
        });

        $this->directiveContainer->singleton(DirectiveInteractionService::class, function ($c) {
            return new DirectiveInteractionService(
                $c->make(RenderDispatcher::class),
                $c->make(InputDispatcher::class),
            );
        });

        $this->directiveContainer->singleton(SignatureValidationService::class, function () {
            return new SignatureValidationService(new EnvSignatureValidationConfig);
        });

        $this->directiveContainer->singleton(DirectiveNamingService::class, function () {
            return new DirectiveNamingService(new EnvDirectiveNamingConfig);
        });

        $this->directiveContainer->singleton(LaravelBootstrapperContext::class, function () {
            $bootstrapperContext = new LaravelBootstrapperContext;

            if ($this->laravelApp !== null) {
                $bootstrapPath = $this->directiveTempDir . '/bootstrap/app.php';
                $bootstrapperContext->setCustomBootstrapPath($bootstrapPath);
            }

            return $bootstrapperContext;
        });

        // Register directive discovery context
        $this->directiveContainer->singleton(DirectiveDiscoveryContext::class, function () {
            return new DirectiveDiscoveryContext;
        });

        $directiveConfig = new EnvDirectiveConfig;
        $this->directiveContainer->instance(DirectiveConfigInterface::class, $directiveConfig);

        $factory = new ContainerDirectiveFactory($this->directiveContainer);
        $parser = new DirectiveParserService;

        $laravelBootstrapperContext = $this->directiveContainer->make(LaravelBootstrapperContext::class);

        // Hydrator with dependencies injected via constructor
        $hydrator = new DirectiveHydratorService($factory, $laravelBootstrapperContext);

        $this->interaction = $this->directiveContainer->make(DirectiveInteractionService::class);
        $signatureValidator = $this->directiveContainer->make(SignatureValidationService::class);
        $namingService = $this->directiveContainer->make(DirectiveNamingService::class);

        $this->directiveRegistry = new TestDirectiveRegistry;

        $discoveryContext = $this->directiveContainer->make(DirectiveDiscoveryContext::class);

        // Discovery service with all dependencies injected via constructor
        $discovery = new DirectiveDiscoveryService(
            config: $directiveConfig,
            hydrator: $hydrator,
            context: $discoveryContext,
            laravelBootstrapperContext: $laravelBootstrapperContext,
            loader: null,
        );

        $renderer = new DirectiveRendererService($this->directiveContainer->make(RenderDispatcher::class));
        $signatureValidatorService = $this->directiveContainer->make(SignatureValidationService::class);

        // Execution service with all dependencies injected via constructor
        $executionService = new DirectiveExecutionService(
            discovery: $discovery,
            parser: $parser,
            hydrator: $hydrator,
            renderer: $renderer,
            laravelBootstrapperContext: $laravelBootstrapperContext,
        );

        $this->directiveKernel = new DirectiveKernel(
            $executionService,
            $signatureValidatorService,
            $renderer,
        );

        $this->directiveTestingInitialized = true;
    }

    /**
     * Creates a minimal Laravel application structure for testing.
     */
    private function createLaravelStructure(): void
    {
        $bootstrapDir = $this->directiveTempDir . '/bootstrap';
        mkdir($bootstrapDir, 0777, true);

        $appPhp = <<<'PHP'
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
        file_put_contents($bootstrapDir . '/app.php', $appPhp);

        $configDir = $this->directiveTempDir . '/config';
        mkdir($configDir, 0777, true);

        $configApp = <<<'PHP'
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
        Illuminate\Foundation\Providers\ArtisanServiceProvider::class,
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,
    ],
];
PHP;
        file_put_contents($configDir . '/app.php', $configApp);

        $storageDir = $this->directiveTempDir . '/storage';
        mkdir($storageDir, 0777, true);
        mkdir($storageDir . '/framework', 0777, true);
        mkdir($storageDir . '/framework/views', 0777, true);
        mkdir($storageDir . '/framework/cache', 0777, true);
        mkdir($storageDir . '/logs', 0777, true);

        mkdir($this->directiveTempDir . '/app', 0777, true);
        mkdir($this->directiveTempDir . '/app/Http', 0777, true);
        mkdir($this->directiveTempDir . '/app/Models', 0777, true);
    }

    /**
     * Creates a Laravel application instance from the temporary structure.
     *
     * @return Application The Laravel application instance
     */
    public function createApplication(): Application
    {
        $app = require $this->directiveTempDir . '/bootstrap/app.php';

        $app->useStoragePath($this->directiveTempDir . '/storage');
        $app->instance('path.config', $this->directiveTempDir . '/config');

        return $app;
    }

    /**
     * Registers a directive instance for testing.
     *
     * @param  AbstractDirective  $directive  The directive instance to register
     */
    protected function registerDirective(AbstractDirective $directive): void
    {
        $this->initDirectiveTesting($this->bootLaravelEnabled);
        $this->directiveRegistry->register($directive);
    }

    /**
     * Registers multiple directive instances for testing.
     *
     * @param  array<AbstractDirective>  $directives  The directive instances to register
     */
    protected function registerDirectives(array $directives): void
    {
        $this->initDirectiveTesting($this->bootLaravelEnabled);
        $this->directiveRegistry->registerAll($directives);
    }

    /**
     * Clears all registered directives from the registry.
     */
    protected function clearRegisteredDirectives(): void
    {
        if ($this->directiveTestingInitialized) {
            $this->directiveRegistry->clear();
        }
    }

    /**
     * Creates a temporary test directive with a closure as execution logic.
     *
     * @param  string  $signature  The directive signature
     * @param  callable  $execute  The execution logic
     * @return ClosureDirective The created directive instance
     */
    protected function createTestDirective(string $signature, callable $execute): ClosureDirective
    {
        $this->initDirectiveTesting($this->bootLaravelEnabled);

        $directive = new ClosureDirective(
            signature: $signature,
            execute: $execute,
            interaction: $this->interaction,
        );

        $this->registerDirective($directive);

        return $directive;
    }

    /**
     * Runs a directive by its FQCN (fully qualified class name).
     *
     * @param  string  $className  FQCN of the directive (e.g., App\Directives\MyDirective::class)
     * @param  array<string>  $arguments  The arguments to pass to the directive
     * @return DirectiveResponseRecord The response containing exit code and output
     */
    protected function runDirective(string $className, array $arguments = []): DirectiveResponseRecord
    {
        $this->initDirectiveTesting($this->bootLaravelEnabled);

        $directive = $this->directiveRegistry->getDirective($className);

        if ($directive !== null) {
            return $this->executeDirectly($directive, $arguments);
        }

        // Fallback: try via the kernel with the signature
        $argv = array_merge(['directive', $className], $arguments);

        ob_start();
        $exitCode = $this->directiveKernel->run($argv);
        $output = ob_get_clean();

        return new DirectiveResponseRecord(
            exitCode: $exitCode,
            output: $output,
        );
    }

    /**
     * Executes a directive directly without going through the kernel.
     *
     * @param  AbstractDirective  $directive  The directive instance
     * @param  array<string>  $arguments  The arguments to pass
     * @return DirectiveResponseRecord The response containing exit code and output
     */
    private function executeDirectly(AbstractDirective $directive, array $arguments = []): DirectiveResponseRecord
    {
        $fullSignature = $directive->getSignature();
        $parser = new DirectiveParserService;

        $argumentCollection = new StringTypedCollection;
        foreach ($arguments as $argument) {
            $argumentCollection->add($argument);
        }

        $bufferStarted = false;

        try {
            $parsed = $parser->parse($fullSignature, $argumentCollection);

            if (method_exists($directive, 'setArguments')) {
                $directive->setArguments(
                    ParameterCollection::fromFlatArguments($parsed->arguments)
                );
            }

            if (method_exists($directive, 'setOptions')) {
                $directive->setOptions(
                    ParameterCollection::fromFlatOptions($parsed->options)
                );
            }

            ob_start();
            $bufferStarted = true;
            $exitCode = $directive->execute();
            $output = ob_get_clean();

            return new DirectiveResponseRecord(
                exitCode: $exitCode,
                output: $output,
            );
        } catch (InvalidArgumentException $e) {
            if ($bufferStarted) {
                ob_end_clean();
            }

            return new DirectiveResponseRecord(
                exitCode: ExitCode::INVALID_ARGUMENT,
                output: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            if ($bufferStarted) {
                ob_end_clean();
            }

            return new DirectiveResponseRecord(
                exitCode: ExitCode::FAILURE,
                output: $e->getMessage(),
            );
        }
    }

    /**
     * Returns the current output buffer level.
     *
     * Useful for debugging buffer-related issues in tests.
     *
     * @return int The current output buffer level
     */
    protected function getBufferLevel(): int
    {
        return ob_get_level();
    }

    /**
     * Destroys the testing environment and cleans up temporary files.
     */
    protected function destroyDirectiveTesting(): void
    {
        if (! $this->directiveTestingInitialized) {
            return;
        }

        $this->clearRegisteredDirectives();

        if (isset($this->directiveTempDir) && is_dir($this->directiveTempDir)) {
            $this->removeDirectory($this->directiveTempDir);
        }

        if (isset($this->originalCwd)) {
            chdir($this->originalCwd);
        }

        $this->laravelApp = null;
        $this->bootLaravelEnabled = false;
        $this->directiveTestingInitialized = false;
    }

    /**
     * Recursively removes a directory and all its contents.
     *
     * @param  string  $dir  The directory path to remove
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
