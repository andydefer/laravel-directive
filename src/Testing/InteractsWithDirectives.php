<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Configs\EnvDirectiveConfig;
use AndyDefer\Directive\Configs\EnvDirectiveNamingConfig;
use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\DirectiveDiscoveryContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\PhpServices\Enums\PrimitiveType;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpServices\Services\PrimitiveTypeConverterService;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use InvalidArgumentException;

/**
 * @deprecated since version 3.8.0, will be removed in 4.0.0
 *
 * Use DirectiveTestingService instead.
 *
 * Reasons for deprecation:
 * - Implicit coupling and hidden state
 * - Violates Single Responsibility Principle
 * - Forces inheritance over composition
 * - Untraceable execution flow
 * - Difficult to test in isolation
 * @see DirectiveTestingService
 * @see DirectiveTestingContext
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

    private ?LaravelBootstrapperContext $laravelBootstrapperContext = null;

    private PrimitiveTypeConverterService $typeConverter;

    protected function initDirectiveTesting(bool $bootLaravel = false): void
    {
        if ($this->directiveTestingInitialized) {
            return;
        }

        $this->typeConverter = new PrimitiveTypeConverterService();
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

        $this->directiveContainer->singleton(DirectiveDiscoveryContext::class, function () {
            return new DirectiveDiscoveryContext;
        });

        $directiveConfig = new EnvDirectiveConfig;
        $this->directiveContainer->instance(DirectiveConfigInterface::class, $directiveConfig);

        $parser = new DirectiveParserService;

        $this->laravelBootstrapperContext = $this->directiveContainer->make(LaravelBootstrapperContext::class);
        $this->interaction = $this->directiveContainer->make(DirectiveInteractionService::class);

        $hydrator = new DirectiveHydratorService(
            laravelBootstrapperContext: $this->laravelBootstrapperContext,
            interaction: $this->interaction,
        );

        $this->directiveRegistry = new TestDirectiveRegistry;

        $discoveryContext = $this->directiveContainer->make(DirectiveDiscoveryContext::class);

        $discovery = new DirectiveDiscoveryService(
            config: $directiveConfig,
            hydrator: $hydrator,
            context: $discoveryContext,
            laravelBootstrapperContext: $this->laravelBootstrapperContext,
            loader: null,
        );

        $renderer = new DirectiveRendererService($this->directiveContainer->make(RenderDispatcher::class));
        $signatureValidatorService = $this->directiveContainer->make(SignatureValidationService::class);

        $executionService = new DirectiveExecutionService(
            discovery: $discovery,
            parser: $parser,
            hydrator: $hydrator,
            renderer: $renderer,
            laravelBootstrapperContext: $this->laravelBootstrapperContext,
        );

        $this->directiveKernel = new DirectiveKernel(
            $executionService,
            $signatureValidatorService,
            $renderer,
        );

        $this->directiveTestingInitialized = true;
    }

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

    public function createApplication(): Application
    {
        $app = require $this->directiveTempDir . '/bootstrap/app.php';

        $app->useStoragePath($this->directiveTempDir . '/storage');
        $app->instance('path.config', $this->directiveTempDir . '/config');

        return $app;
    }

    protected function registerDirective(AbstractDirective $directive): void
    {
        $this->initDirectiveTesting($this->bootLaravelEnabled);
        $this->directiveRegistry->register($directive);
    }

    protected function registerDirectives(array $directives): void
    {
        $this->initDirectiveTesting($this->bootLaravelEnabled);
        $this->directiveRegistry->registerAll($directives);
    }

    protected function clearRegisteredDirectives(): void
    {
        if ($this->directiveTestingInitialized) {
            $this->directiveRegistry->clear();
        }
    }

    protected function createTestDirective(string $signature, callable $execute): ClosureDirective
    {
        $this->initDirectiveTesting($this->bootLaravelEnabled);

        $context = new DirectiveContext(
            laravelBootstrapper: $this->laravelBootstrapperContext ?? new LaravelBootstrapperContext,
            blueprint: new DirectiveBlueprintRecord(ClosureDirective::class, $signature, 'Test directive created from closure'),
            aliases: new StringTypedCollection,
            shouldBootLaravel: false,
        );

        $directive = new ClosureDirective(
            context: $context,
            interaction: $this->interaction,
            signature: $signature,
            execute: $execute,
        );

        $this->registerDirective($directive);

        return $directive;
    }

    protected function runDirective(string $className, array $arguments = []): DirectiveResponseRecord
    {
        $this->initDirectiveTesting($this->bootLaravelEnabled);

        $directive = $this->directiveRegistry->getDirective($className);

        if ($directive !== null) {
            return $this->executeDirectly($directive, $arguments);
        }

        $argv = array_merge(['directive', $className], $arguments);

        ob_start();
        $exit_code = $this->directiveKernel->run($argv);
        $output = ob_get_clean();

        return new DirectiveResponseRecord(
            exit_code: $exit_code,
            output: $output,
        );
    }

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

            $context = new DirectiveContext(
                laravelBootstrapper: $this->laravelBootstrapperContext ?? new LaravelBootstrapperContext,
                blueprint: $directive->getBlueprint(),
                aliases: $directive->getAliases(),
                shouldBootLaravel: $directive->shouldBootLaravel(),
            );

            // Convertir les arguments parsés en ParameterVOCollection
            $argumentsVO = new ParameterVOCollection;
            foreach ($parsed->arguments as $name => $value) {
                $detectedType = $this->typeConverter->detectType($value);
                $convertedValue = $this->typeConverter->convert($value, $detectedType);
                $argumentsVO->add(new ParameterVO($name, $convertedValue, $detectedType));
            }

            // Convertir les options parsées en ParameterVOCollection
            $optionsVO = new ParameterVOCollection;
            foreach ($parsed->options as $option) {
                $value = $option->value;
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                }
                $detectedType = $this->typeConverter->detectType($value);
                $optionsVO->add(new ParameterVO($option->name, $value, $detectedType));
            }

            $context->setArguments($argumentsVO);
            $context->setOptions($optionsVO);
            $context->setVariadicArguments($parsed->variadic_arguments);

            $reflection = new \ReflectionClass($directive);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                ob_start();
                $bufferStarted = true;
                $exit_code = $directive->execute();
                $output = ob_get_clean();

                return new DirectiveResponseRecord(exit_code: $exit_code, output: $output);
            }

            $parameters = $constructor->getParameters();
            $args = [];

            foreach ($parameters as $param) {
                $paramType = $param->getType();

                if ($paramType === null) {
                    // Paramètre sans type, essayer de récupérer la valeur par réflexion
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        $args[] = null;
                    }

                    continue;
                }

                $paramName = $paramType->getName();

                if ($paramName === DirectiveContext::class) {
                    $args[] = $context;
                } elseif ($paramName === DirectiveInteractionService::class) {
                    $args[] = $this->interaction;
                } elseif ($paramName === 'string') {
                    // Pour le paramètre signature de ClosureDirective
                    if ($param->getName() === 'signature') {
                        $args[] = $fullSignature;
                    } else {
                        $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                    }
                } elseif ($paramName === 'Closure' || $paramName === 'callable' || $paramName === '\\Closure') {
                    // Pour le paramètre execute de ClosureDirective
                    // Récupérer la valeur de la propriété execute via réflexion
                    try {
                        $executeProperty = $reflection->getProperty('execute');
                        $args[] = $executeProperty->getValue($directive);
                    } catch (\ReflectionException $e) {
                        $args[] = null;
                    }
                } elseif ($paramType->isBuiltin()) {
                    // Types scalaires (int, bool, float, etc.)
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        $args[] = null;
                    }
                } else {
                    // Autres types d'objets
                    $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                }
            }

            $hydratedDirective = $reflection->newInstanceArgs($args);

            ob_start();
            $bufferStarted = true;
            $exit_code = $hydratedDirective->execute();
            $output = ob_get_clean();

            return new DirectiveResponseRecord(
                exit_code: $exit_code,
                output: $output,
            );
        } catch (InvalidArgumentException $e) {
            if ($bufferStarted) {
                ob_end_clean();
            }

            return new DirectiveResponseRecord(
                exit_code: ExitCode::INVALID_ARGUMENT,
                output: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            if ($bufferStarted) {
                ob_end_clean();
            }

            return new DirectiveResponseRecord(
                exit_code: ExitCode::FAILURE,
                output: $e->getMessage(),
            );
        }
    }

    protected function getBufferLevel(): int
    {
        return ob_get_level();
    }

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
        $this->laravelBootstrapperContext = null;
        $this->bootLaravelEnabled = false;
        $this->directiveTestingInitialized = false;
    }

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
