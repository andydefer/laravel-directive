<?php

// src/Services/DirectiveTestingService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Enums\ExitCode;
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

final class DirectiveTestingService
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

    public function registerDirective(AbstractDirective $directive): void
    {
        $signature = $directive->getSignature();
        $this->context->getRegistry()->register($directive);
    }

    public function registerDirectives(array $directives): void
    {
        foreach ($directives as $directive) {
            $this->registerDirective($directive);
        }
    }

    public function clearRegisteredDirectives(): void
    {
        $this->context->getRegistry()->clear();
        $this->context->getClosureRegistry()->clear();
    }

    public function createTestDirective(string $signature, callable $execute): ClosureDirective
    {

        $context = new DirectiveContext(
            laravelBootstrapper: $this->laravelBootstrapperContext ?? new LaravelBootstrapperContext,
            blueprint: new \AndyDefer\Directive\Records\DirectiveBlueprintRecord(ClosureDirective::class, $signature, 'Test directive created from closure'),
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

    public function runDirective(string $className, array $arguments = []): DirectiveResponseRecord
    {

        $directive = $this->context->getClosureRegistry()->get($className);
        if ($directive !== null) {
            if ($directive->shouldBootLaravel() && !$this->context->isIntegratedMode() && !$this->context->isInitialized()) {
                $this->initializeIsolatedEnvironment();
            }
            return $this->executeDirectly($directive, $arguments);
        }

        $directive = $this->context->getRegistry()->getDirective($className);
        if ($directive !== null) {
            if ($directive->shouldBootLaravel() && !$this->context->isIntegratedMode() && !$this->context->isInitialized()) {
                $this->initializeIsolatedEnvironment();
            }
            return $this->executeDirectly($directive, $arguments);
        }


        $kernel = $this->context->getKernel();

        if ($kernel === null) {
            return new DirectiveResponseRecord(
                exitCode: ExitCode::NOT_FOUND,
                output: "Kernel not available. Cannot execute directive: {$className}",
            );
        }

        $argv = array_merge(['directive', $className], $arguments);

        ob_start();
        $exitCode = $kernel->run($argv);
        $output = ob_get_clean();

        $this->context->addExecutedDirective($className);

        return new DirectiveResponseRecord(
            exitCode: $exitCode,
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
                    // Type scalaire (int, bool, float, etc.)
                    $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                } else {
                    // C'est un objet ! Utiliser le conteneur pour l'instancier
                    try {
                        if ($this->context->isIntegratedMode() && $this->context->getLaravelApp() !== null) {
                            $args[] = $this->context->getLaravelApp()->make($paramName);
                        } else {
                            // Fallback: essayer de créer l'instance sans paramètres
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

    public function getInteraction(): DirectiveInteractionService
    {
        return $this->context->getInteraction();
    }

    public function getContext(): DirectiveTestingContext
    {
        return $this->context;
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
