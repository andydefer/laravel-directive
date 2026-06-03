<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\DirectiveKernel;
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
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
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
     * @param bool $bootLaravel Whether to bootstrap Laravel for tests that need it
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

        $this->directiveContainer = new Container();

        $this->directiveContainer->singleton(RenderDispatcher::class, function () {
            return new RenderDispatcher();
        });
        $this->directiveContainer->singleton(InputDispatcher::class, function () {
            return new InputDispatcher();
        });

        $this->directiveContainer->singleton(DirectiveInteractionService::class, function ($c) {
            return new DirectiveInteractionService(
                $c->make(RenderDispatcher::class),
                $c->make(InputDispatcher::class),
            );
        });

        $this->directiveContainer->singleton(SignatureValidationService::class, function () {
            return new SignatureValidationService();
        });

        $this->directiveContainer->singleton(DirectiveNamingService::class, function () {
            return new DirectiveNamingService();
        });

        $this->directiveContainer->singleton(LaravelBootstrapper::class, function () {
            $bootstrapper = new LaravelBootstrapper();

            if ($this->laravelApp !== null) {
                $bootstrapPath = $this->directiveTempDir . '/bootstrap/app.php';
                $bootstrapper->setCustomBootstrapPath($bootstrapPath);
            }

            return $bootstrapper;
        });

        $directiveConfig = DirectiveConfig::default()->withDirectivesPath($this->directiveTempDir . '/app/Directives');
        $this->directiveContainer->instance(DirectiveConfig::class, $directiveConfig);

        $factory = new ContainerDirectiveFactory($this->directiveContainer);
        $parser = new DirectiveParserService();
        $hydrator = new DirectiveHydratorService($factory);
        $laravelBootstrapper = $this->directiveContainer->make(LaravelBootstrapper::class);
        $hydrator->setLaravelBootstrapper($laravelBootstrapper);

        $this->interaction = $this->directiveContainer->make(DirectiveInteractionService::class);
        $signatureValidator = $this->directiveContainer->make(SignatureValidationService::class);
        $namingService = $this->directiveContainer->make(DirectiveNamingService::class);

        $this->directiveRegistry = new TestDirectiveRegistry();

        $discovery = new DirectiveDiscoveryService($directiveConfig, $hydrator, $this->directiveRegistry);
        $discovery->setLaravelBootstrapper($laravelBootstrapper);

        $renderer = new DirectiveRendererService($this->directiveContainer->make(RenderDispatcher::class));
        $signatureValidatorService = $this->directiveContainer->make(SignatureValidationService::class);

        $executionService = new DirectiveExecutionService(
            discovery: $discovery,
            parser: $parser,
            hydrator: $hydrator,
            renderer: $renderer,
        );
        $executionService->setLaravelBootstrapper($laravelBootstrapper);

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

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    Illuminate\Foundation\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    Illuminate\Foundation\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    Illuminate\Foundation\Exceptions\Handler::class
);

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
    'providers' => [],
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
     * @param AbstractDirective $directive The directive instance to register
     */
    protected function registerDirective(AbstractDirective $directive): void
    {
        $this->initDirectiveTesting($this->bootLaravelEnabled);
        $this->directiveRegistry->register($directive);
    }

    /**
     * Registers multiple directive instances for testing.
     *
     * @param array<AbstractDirective> $directives The directive instances to register
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
     * @param string   $signature The directive signature
     * @param callable $execute   The execution logic
     *
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
     * @param string         $className FQCN of the directive (e.g., App\Directives\MyDirective::class)
     * @param array<string>  $arguments The arguments to pass to the directive
     *
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
     * @param AbstractDirective $directive The directive instance
     * @param array<string>     $arguments The arguments to pass
     *
     * @return DirectiveResponseRecord The response containing exit code and output
     */
    private function executeDirectly(AbstractDirective $directive, array $arguments = []): DirectiveResponseRecord
    {
        $fullSignature = $directive->getSignature();
        $parser = new DirectiveParserService();

        $argumentCollection = new StringTypedCollection();
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
        if (!$this->directiveTestingInitialized) {
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
     * @param string $dir The directory path to remove
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
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
