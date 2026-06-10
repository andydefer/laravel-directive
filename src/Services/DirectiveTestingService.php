<?php

// src/Services/DirectiveTestingService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contracts\DirectiveTestingServiceInterface;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use AndyDefer\Directive\Steps\BootstrapLaravelStep;
use AndyDefer\Directive\Steps\BuildContainerStep;
use AndyDefer\Directive\Steps\ChangeToTempDirectoryStep;
use AndyDefer\Directive\Steps\CreateLaravelStructureStep;
use AndyDefer\Directive\Steps\CreateTempDirectoryStep;
use AndyDefer\Directive\Steps\DirectiveTestingStepInterface;
use AndyDefer\Directive\Steps\StartDatabaseStep;
use AndyDefer\Directive\Testing\ClosureDirective;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use InvalidArgumentException;

final class DirectiveTestingService implements DirectiveTestingServiceInterface
{
    private DirectiveTestingContext $context;
    private ?LaravelBootstrapperContext $laravelBootstrapperContext = null;
    private ?DirectiveInteractionService $interaction = null;

    public function __construct(
        ?object $application = null,
        ?DirectiveTestingContext $context = null,
        ?DirectiveTestingConfigInterface $config = null,
    ) {
        $this->context = $context ?? new DirectiveTestingContext;
        $this->context->setConfig($config ?? new DirectiveTestingConfig);

        $this->initializeInteraction();

        if ($application !== null) {
            $this->context->setIntegratedMode(true);
            $this->context->setLaravelApp($application);
            $this->context->setBootLaravel(true);

            $kernel = $application->make(DirectiveKernel::class);
            $this->context->setKernel($kernel);

            $this->laravelBootstrapperContext = $application->make(LaravelBootstrapperContext::class);
            return;
        }

        $this->initializeMinimalEnvironment();
    }

    private function initializeInteraction(): void
    {
        $renderDispatcher = new RenderDispatcher;
        $inputDispatcher = new InputDispatcher;

        $this->interaction = new DirectiveInteractionService($renderDispatcher, $inputDispatcher);
        $this->context->setInteraction($this->interaction);
    }

    private function initializeMinimalEnvironment(): void
    {
        $step = new BuildContainerStep;
        $step->execute($this->context, fn($c) => $c);

        $container = $this->context->getContainer();
        if ($container !== null && $container->has(LaravelBootstrapperContext::class)) {
            $this->laravelBootstrapperContext = $container->make(LaravelBootstrapperContext::class);
        }

        if ($this->laravelBootstrapperContext === null) {
            $this->laravelBootstrapperContext = new LaravelBootstrapperContext;
        }
    }

    private function initializeIsolatedEnvironment(): void
    {
        if ($this->context->isInitialized()) {
            return;
        }

        $steps = [
            new CreateTempDirectoryStep,
            new ChangeToTempDirectoryStep,
            new CreateLaravelStructureStep,
            new BootstrapLaravelStep,
            new BuildContainerStep,
            new StartDatabaseStep,
        ];

        $this->executeSteps($steps);
        $this->context->setInitialized(true);

        $container = $this->context->getContainer();
        if ($container !== null && $container->has(LaravelBootstrapperContext::class)) {
            $this->laravelBootstrapperContext = $container->make(LaravelBootstrapperContext::class);
        }

        if ($this->laravelBootstrapperContext === null) {
            $this->laravelBootstrapperContext = new LaravelBootstrapperContext;
        }
    }

    private function executeSteps(array $steps): void
    {
        $chain = $this->buildChain($steps);
        $chain($this->context);
    }

    private function buildChain(array $steps): callable
    {
        $next = function (DirectiveTestingContext $context) {
            return $context;
        };

        for ($i = count($steps) - 1; $i >= 0; $i--) {
            $step = $steps[$i];
            $currentNext = $next;
            $next = function (DirectiveTestingContext $context) use ($step, $currentNext) {
                if ($step->supports($context)) {
                    return $step->execute($context, $currentNext);
                }

                return $currentNext($context);
            };
        }

        return $next;
    }

    // ==================== Enregistrement par classe ====================

    public function registerDirective(string $class): void
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Directive class {$class} does not exist");
        }

        if ($this->context->isIntegratedMode() && $this->context->getLaravelApp() !== null) {
            $directive = $this->context->getLaravelApp()->make($class);
        } else {
            $directive = $this->createDirectiveInstance($class);
        }

        $this->context->getRegistry()->register($directive);
    }

    public function registerDirectives(array $classes): void
    {
        foreach ($classes as $class) {
            $this->registerDirective($class);
        }
    }

    // ==================== Enregistrement par instance ====================

    public function registerDirectiveInstance(AbstractDirective $directive): void
    {
        $this->context->getRegistry()->register($directive);
    }

    public function registerDirectiveInstances(array $directives): void
    {
        foreach ($directives as $directive) {
            $this->registerDirectiveInstance($directive);
        }
    }

    // ==================== Enregistrement + Exécution ====================

    public function registerAndRun(string $class, array $arguments = []): DirectiveResponseRecord
    {
        $this->registerDirective($class);
        $signature = $this->extractSignatureFromClass($class);
        return $this->runDirective($signature, $arguments);
    }

    public function registerAndRunInstance(AbstractDirective $directive, array $arguments = []): DirectiveResponseRecord
    {
        $this->registerDirectiveInstance($directive);
        return $this->runDirective($directive->getSignature(), $arguments);
    }

    // ==================== Exécution ====================

    public function run(string $class, array $arguments = []): DirectiveResponseRecord
    {
        return $this->registerAndRun($class, $arguments);
    }

    public function runDirective(string $signature, array $arguments = []): DirectiveResponseRecord
    {
        // Vérifier d'abord dans ClosureRegistry
        $directive = $this->context->getClosureRegistry()->get($signature);
        if ($directive !== null) {
            if ($directive->shouldBootLaravel() && !$this->context->isIntegratedMode() && !$this->context->isInitialized()) {
                $this->initializeIsolatedEnvironment();
            }
            return $this->executeDirectly($directive, $arguments);
        }

        // Vérifier dans Registry
        $directive = $this->context->getRegistry()->getDirective($signature);
        if ($directive !== null) {
            if ($directive->shouldBootLaravel() && !$this->context->isIntegratedMode() && !$this->context->isInitialized()) {
                $this->initializeIsolatedEnvironment();
            }
            return $this->executeDirectly($directive, $arguments);
        }

        // Fallback via Kernel
        $kernel = $this->context->getKernel();

        if ($kernel === null) {
            return new DirectiveResponseRecord(
                exitCode: ExitCode::NOT_FOUND,
                output: "Kernel not available. Cannot execute directive: {$signature}",
            );
        }

        $argv = array_merge(['directive', $signature], $arguments);

        ob_start();
        $exitCode = $kernel->run($argv);
        $output = ob_get_clean();

        $this->context->addExecutedDirective($signature);

        return new DirectiveResponseRecord(
            exitCode: $exitCode,
            output: $output,
        );
    }

    // ==================== Méthodes utilitaires ====================

    public function createTestDirective(string $signature, callable $execute): ClosureDirective
    {
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

        $this->context->getClosureRegistry()->register($directive);

        return $directive;
    }

    public function clearRegisteredDirectives(): void
    {
        $this->context->getRegistry()->clear();
        $this->context->getClosureRegistry()->clear();
    }

    public function destroy(): void
    {
        $this->clearRegisteredDirectives();
        $this->context->getClosureRegistry()->clear();

        $tempDir = $this->context->getTempDir();
        if ($tempDir !== null && is_dir($tempDir) && $this->context->getConfig()->cleanupAfterTest()) {
            $this->removeDirectory($tempDir);
        }

        $originalCwd = $this->context->getOriginalCwd();
        if ($originalCwd !== null) {
            chdir($originalCwd);
        }

        $this->context->reset();
    }

    public function getInteraction(): DirectiveInteractionService
    {
        return $this->context->getInteraction();
    }

    public function getContext(): DirectiveTestingContext
    {
        return $this->context;
    }

    // ==================== Méthodes privées ====================

    private function extractSignatureFromClass(string $class): string
    {
        if ($this->context->isIntegratedMode() && $this->context->getLaravelApp() !== null) {
            $directive = $this->context->getLaravelApp()->make($class);
            return $directive->getSignature();
        }

        $reflection = new \ReflectionClass($class);
        $directive = $reflection->newInstanceWithoutConstructor();

        if (!method_exists($directive, 'getSignature')) {
            throw new \RuntimeException("Class {$class} does not have a getSignature method");
        }

        return $directive->getSignature();
    }

    private function createDirectiveInstance(string $class): AbstractDirective
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $parameters = $constructor->getParameters();
        $args = [];

        foreach ($parameters as $param) {
            $paramType = $param->getType();
            if ($paramType === null || $paramType->isBuiltin()) {
                $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
            } else {
                $paramName = $paramType->getName();
                if ($paramName === DirectiveContext::class) {
                    $args[] = new DirectiveContext(
                        laravelBootstrapper: $this->laravelBootstrapperContext ?? new LaravelBootstrapperContext,
                        blueprint: new DirectiveBlueprintRecord($class, '', ''),
                        aliases: new StringTypedCollection,
                        shouldBootLaravel: false,
                    );
                } elseif ($paramName === DirectiveInteractionService::class) {
                    $args[] = $this->interaction;
                } else {
                    $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                }
            }
        }

        return $reflection->newInstanceArgs($args);
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

            $context->setArguments(ParameterCollection::fromFlatArguments($parsed->arguments));
            $context->setOptions(ParameterCollection::fromFlatOptions($parsed->options));
            $context->setVariadicArguments($parsed->variadic_arguments);

            $reflection = new \ReflectionClass($directive);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return new DirectiveResponseRecord(
                    exitCode: ExitCode::FAILURE,
                    output: "Directive has no constructor",
                );
            }

            $parameters = $constructor->getParameters();
            $args = [];

            foreach ($parameters as $param) {
                $paramType = $param->getType();

                if ($paramType === null) {
                    if ($param->getName() === 'execute') {
                        $executeProperty = $reflection->getProperty('execute');
                        $args[] = $executeProperty->getValue($directive);
                    } else {
                        $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                    }
                    continue;
                }

                $paramName = $paramType->getName();

                if ($paramName === DirectiveContext::class) {
                    $args[] = $context;
                } elseif ($paramName === DirectiveInteractionService::class) {
                    $args[] = $this->interaction;
                } elseif ($paramName === 'string') {
                    if ($param->getName() === 'signature') {
                        $args[] = $fullSignature;
                    } else {
                        $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                    }
                } elseif ($paramName === 'Closure' || $paramName === 'callable' || $paramName === '\\Closure') {
                    $executeProperty = $reflection->getProperty('execute');
                    $args[] = $executeProperty->getValue($directive);
                } elseif ($paramType->isBuiltin()) {
                    $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                } else {
                    try {
                        if ($this->context->isIntegratedMode() && $this->context->getLaravelApp() !== null) {
                            $args[] = $this->context->getLaravelApp()->make($paramName);
                        } else {
                            $objClass = new \ReflectionClass($paramName);
                            if ($objClass->isInstantiable()) {
                                $args[] = $objClass->newInstance();
                            } else {
                                $args[] = null;
                            }
                        }
                    } catch (\Exception $e) {
                        $args[] = null;
                    }
                }
            }

            $hydratedDirective = $reflection->newInstanceArgs($args);

            ob_start();
            $bufferStarted = true;
            $exitCode = $hydratedDirective->execute();
            $output = ob_get_clean();

            $directiveName = explode(' ', $fullSignature)[0];
            $this->context->addExecutedDirective($directiveName);

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
