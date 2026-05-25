<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Factories\ContainerDirectiveFactory;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Tasks\InputTask;
use AndyDefer\Directive\Tasks\RenderTask;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use Illuminate\Container\Container;

trait InteractsWithDirectives
{
    private Container $directiveContainer;
    private DirectiveKernel $directiveKernel;
    private TestDirectiveRegistry $directiveRegistry;
    private bool $directiveTestingInitialized = false;
    private string $directiveTempDir;
    private string $originalCwd;

    protected function initDirectiveTesting(): void
    {
        if ($this->directiveTestingInitialized) {
            return;
        }

        $this->directiveTempDir = sys_get_temp_dir() . '/directive_test_' . uniqid();
        mkdir($this->directiveTempDir, 0777, true);

        $this->originalCwd = getcwd();
        chdir($this->directiveTempDir);

        $this->directiveContainer = new Container();

        $this->directiveContainer->singleton(RenderTask::class, function () {
            return new RenderTask();
        });
        $this->directiveContainer->singleton(InputTask::class, function () {
            return new InputTask();
        });

        $this->directiveContainer->singleton(DirectiveInteractionService::class, function ($c) {
            return new DirectiveInteractionService(
                $c->make(RenderTask::class),
                $c->make(InputTask::class),
            );
        });

        $this->directiveContainer->singleton(SignatureValidationService::class, function () {
            return new SignatureValidationService();
        });

        $this->directiveContainer->singleton(DirectiveNamingService::class, function () {
            return new DirectiveNamingService();
        });

        $this->directiveContainer->singleton(LaravelBootstrapper::class, function () {
            return new LaravelBootstrapper();
        });

        $directiveConfig = DirectiveConfig::default()->withDirectivesPath($this->directiveTempDir . '/app/Directives');
        $this->directiveContainer->instance(DirectiveConfig::class, $directiveConfig);

        $factory = new ContainerDirectiveFactory($this->directiveContainer);
        $parser = new DirectiveParserService();
        $hydrator = new DirectiveHydratorService($factory);
        $laravelBootstrapper = $this->directiveContainer->make(LaravelBootstrapper::class);
        $hydrator->setLaravelBootstrapper($laravelBootstrapper);

        $interaction = $this->directiveContainer->make(DirectiveInteractionService::class);
        $signatureValidator = $this->directiveContainer->make(SignatureValidationService::class);
        $namingService = $this->directiveContainer->make(DirectiveNamingService::class);

        $this->directiveRegistry = new TestDirectiveRegistry();
        $this->directiveRegistry->setInteraction($interaction);
        $this->directiveRegistry->setSignatureValidator($signatureValidator);
        $this->directiveRegistry->setNamingService($namingService);

        $discovery = new DirectiveDiscoveryService($directiveConfig, $hydrator, $this->directiveRegistry);
        $discovery->setLaravelBootstrapper($laravelBootstrapper);

        $renderer = new DirectiveRendererService($this->directiveContainer->make(RenderTask::class));
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

    protected function registerDirective(AbstractDirective $directive): void
    {
        $this->initDirectiveTesting();
        $this->directiveRegistry->register($directive->getSignature(), $directive);
    }

    protected function registerDirectiveClass(string $className, array $constructorArgs = []): AbstractDirective
    {
        $this->initDirectiveTesting();
        return $this->directiveRegistry->registerByClass($className, $constructorArgs);
    }

    protected function registerDirectives(array $directives): void
    {
        $this->initDirectiveTesting();
        foreach ($directives as $directive) {
            $this->registerDirective($directive);
        }
    }

    protected function clearRegisteredDirectives(): void
    {
        if ($this->directiveTestingInitialized) {
            $this->directiveRegistry->clear();
        }
    }

    protected function createTestDirective(string $signature, callable $execute): ClosureDirective
    {
        $this->initDirectiveTesting();

        $directive = new ClosureDirective(
            signature: $signature,
            execute: $execute,
            interaction: $this->directiveContainer->make(DirectiveInteractionService::class),
        );

        $this->registerDirective($directive);
        return $directive;
    }

    protected function runDirective(string $signature, array $arguments = []): DirectiveResponse
    {
        $this->initDirectiveTesting();

        // Vérifier si la directive est dans le registry
        $directive = $this->directiveRegistry->getDirective($signature);

        if ($directive !== null) {
            // Exécuter directement sans passer par le kernel
            return $this->executeDirectly($directive, $arguments);
        }

        // Sinon passer par le kernel normal
        $argv = array_merge(['directive', $signature], $arguments);

        ob_start();
        $exitCode = $this->directiveKernel->run($argv);
        $output = ob_get_clean();

        return new DirectiveResponse(
            exitCode: $exitCode,
            output: $output,
            arguments: $arguments,
        );
    }

    private function executeDirectly(AbstractDirective $directive, array $arguments = []): DirectiveResponse
    {
        $fullSignature = $directive->getSignature();
        $parser = new DirectiveParserService();

        $argumentCollection = new StringTypedCollection();
        foreach ($arguments as $argument) {
            $argumentCollection->add($argument);
        }

        try {
            $parsed = $parser->parse($fullSignature, $argumentCollection);

            // Utiliser l'instance existante, pas en créer une nouvelle
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
            $exitCode = $directive->execute();
            $output = ob_get_clean();

            return new DirectiveResponse(
                exitCode: $exitCode,
                output: $output,
                arguments: $arguments,
            );
        } catch (\Throwable $e) {
            return new DirectiveResponse(
                exitCode: ExitCode::FAILURE,
                output: $e->getMessage(),
                arguments: $arguments,
            );
        }
    }


    protected function runAndAssert(string $signature, array $arguments = []): DirectiveResponse
    {
        $response = $this->runDirective($signature, $arguments);
        return $response->assertSuccess();
    }

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

        $this->directiveTestingInitialized = false;
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
