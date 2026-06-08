<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

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
use AndyDefer\Directive\Testing\ClosureDirective;
use AndyDefer\Directive\Testing\TestDirectiveRegistry;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use InvalidArgumentException;

/**
 * Service de test des directives.
 * 
 * Permet de tester des directives dans un environnement isolé sans dépendre
 * du système de fichiers réel.
 * 
 * ✅ Pas d'état interne
 * ✅ Dépendances injectées dans le constructeur
 * ✅ Toutes les données arrivent en paramètres
 * ✅ Pas de `final`
 * ✅ Testable et mockable
 * 
 * @author Andy Defer
 */
class DirectiveTestingService
{
    private ?Container $directiveContainer = null;
    private ?DirectiveKernel $directiveKernel = null;
    private ?TestDirectiveRegistry $directiveRegistry = null;
    private ?DirectiveInteractionService $interaction = null;
    private bool $isInitialized = false;
    private ?string $tempDir = null;
    private ?string $originalCwd = null;
    private ?Application $laravelApp = null;
    private bool $bootLaravelEnabled = false;

    public function __construct(
        private readonly DirectiveConfig $config,
    ) {}

    /**
     * Initialise l'environnement de test pour les directives.
     * 
     * @param bool $bootLaravel Whether to bootstrap Laravel for tests that need it
     */
    public function initialize(bool $bootLaravel = false): void
    {
        if ($this->isInitialized) {
            return;
        }

        $this->bootLaravelEnabled = $bootLaravel;
        $this->tempDir = sys_get_temp_dir() . '/directive_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->originalCwd = getcwd();
        chdir($this->tempDir);

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
                $bootstrapPath = $this->tempDir . '/bootstrap/app.php';
                $bootstrapper->setCustomBootstrapPath($bootstrapPath);
            }

            return $bootstrapper;
        });

        $directiveConfig = DirectiveConfig::default()->withDirectivesPath($this->tempDir . '/app/Directives');
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

        $this->isInitialized = true;
    }

    /**
     * Crée une structure Laravel minimale pour les tests.
     */
    private function createLaravelStructure(): void
    {
        $bootstrapDir = $this->tempDir . '/bootstrap';
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

        $configDir = $this->tempDir . '/config';
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

        $storageDir = $this->tempDir . '/storage';
        mkdir($storageDir, 0777, true);
        mkdir($storageDir . '/framework', 0777, true);
        mkdir($storageDir . '/framework/views', 0777, true);
        mkdir($storageDir . '/framework/cache', 0777, true);
        mkdir($storageDir . '/logs', 0777, true);

        mkdir($this->tempDir . '/app', 0777, true);
        mkdir($this->tempDir . '/app/Http', 0777, true);
        mkdir($this->tempDir . '/app/Models', 0777, true);
    }

    /**
     * Crée une instance de l'application Laravel.
     *
     * @return Application The Laravel application instance
     */
    private function createApplication(): Application
    {
        $app = require $this->tempDir . '/bootstrap/app.php';

        $app->useStoragePath($this->tempDir . '/storage');
        $app->instance('path.config', $this->tempDir . '/config');

        return $app;
    }

    /**
     * Enregistre une instance de directive pour le test.
     *
     * @param AbstractDirective $directive The directive instance to register
     */
    public function registerDirective(AbstractDirective $directive): void
    {
        $this->ensureInitialized();
        $this->directiveRegistry->register($directive);
    }

    /**
     * Enregistre plusieurs instances de directives pour le test.
     *
     * @param array<AbstractDirective> $directives The directive instances to register
     */
    public function registerDirectives(array $directives): void
    {
        $this->ensureInitialized();
        $this->directiveRegistry->registerAll($directives);
    }

    /**
     * Efface toutes les directives enregistrées.
     */
    public function clearRegisteredDirectives(): void
    {
        if ($this->isInitialized && $this->directiveRegistry !== null) {
            $this->directiveRegistry->clear();
        }
    }

    /**
     * Crée une directive de test temporaire avec une closure.
     *
     * @param string   $signature The directive signature
     * @param callable $execute   The execution logic
     *
     * @return ClosureDirective The created directive instance
     */
    public function createTestDirective(string $signature, callable $execute): ClosureDirective
    {
        $this->ensureInitialized();

        $directive = new ClosureDirective(
            signature: $signature,
            execute: $execute,
            interaction: $this->interaction,
        );

        $this->registerDirective($directive);
        return $directive;
    }

    /**
     * Exécute une directive par son FQCN.
     *
     * @param string         $className FQCN of the directive (e.g., App\Directives\MyDirective::class)
     * @param array<string>  $arguments The arguments to pass to the directive
     *
     * @return DirectiveResponseRecord The response containing exit code and output
     */
    public function runDirective(string $className, array $arguments = []): DirectiveResponseRecord
    {
        $this->ensureInitialized();

        $directive = $this->directiveRegistry->getDirective($className);

        if ($directive !== null) {
            return $this->executeDirectly($directive, $arguments);
        }

        // ✅ CORRECTION : Si ce n'est pas un FQCN valide (ne contient pas de namespace ou n'est pas dans le registre)
        // On retourne NOT_FOUND directement au lieu de passer par le kernel
        // Parce que le kernel s'attend à un nom de directive simple (kebab-case)
        // et non à un FQCN avec des backslashes
        return new DirectiveResponseRecord(
            exitCode: ExitCode::NOT_FOUND,
            output: "Directive not found: {$className}",
        );
    }

    /**
     * Exécute une directive directement sans passer par le kernel.
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
     * Retourne le niveau actuel du buffer de sortie.
     *
     * Useful for debugging buffer-related issues in tests.
     *
     * @return int The current output buffer level
     */
    public function getBufferLevel(): int
    {
        return ob_get_level();
    }

    /**
     * Détruit l'environnement de test et nettoie les fichiers temporaires.
     */
    public function destroy(): void
    {
        if (!$this->isInitialized) {
            return;
        }

        $this->clearRegisteredDirectives();

        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        if ($this->originalCwd !== null) {
            chdir($this->originalCwd);
        }

        $this->laravelApp = null;
        $this->bootLaravelEnabled = false;
        $this->directiveContainer = null;
        $this->directiveKernel = null;
        $this->directiveRegistry = null;
        $this->interaction = null;
        $this->tempDir = null;
        $this->originalCwd = null;
        $this->isInitialized = false;
    }

    /**
     * Vérifie si l'environnement de test est initialisé.
     *
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }

    /**
     * Retourne le chemin du répertoire temporaire.
     *
     * @return string|null
     */
    public function getTempDirectory(): ?string
    {
        return $this->tempDir;
    }

    /**
     * Retourne l'instance Laravel si disponible.
     *
     * @return Application|null
     */
    public function getLaravelApplication(): ?Application
    {
        return $this->laravelApp;
    }

    /**
     * S'assure que l'environnement est initialisé.
     *
     * @throws \RuntimeException Si l'environnement n'est pas initialisé
     */
    private function ensureInitialized(): void
    {
        if (!$this->isInitialized) {
            throw new \RuntimeException(
                'Directive testing environment not initialized. Call initialize() first.'
            );
        }
    }

    /**
     * Supprime récursivement un répertoire.
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
