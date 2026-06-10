<?php

// src/Services/DirectiveTestingService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
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

    public function __construct(
        ?object $application = null,
        ?DirectiveTestingContext $context = null,
        ?DirectiveTestingConfigInterface $config = null,
    ) {
        // Créer le contexte si non fourni
        $this->context = $context ?? new DirectiveTestingContext;
        $this->context->setConfig($config ?? new DirectiveTestingConfig);

        // Toujours initialiser l'interaction service
        $this->initializeInteraction();

        // Mode intégré : on a une application Laravel existante
        if ($application !== null) {
            $this->context->setIntegratedMode(true);
            $this->context->setLaravelApp($application);
            $this->context->setBootLaravel(true);

            // Créer le kernel à partir de l'application
            $kernel = $application->make(DirectiveKernel::class);
            $this->context->setKernel($kernel);
            return;
        }

        // Mode isolé : on crée l'environnement minimal
        $this->initializeMinimalEnvironment();
    }

    private function initializeInteraction(): void
    {
        $renderDispatcher = new RenderDispatcher;
        $inputDispatcher = new InputDispatcher;

        $interaction = new DirectiveInteractionService($renderDispatcher, $inputDispatcher);
        $this->context->setInteraction($interaction);
    }

    private function initializeMinimalEnvironment(): void
    {
        $step = new BuildContainerStep;
        $step->execute($this->context, fn($c) => $c);
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
        $interaction = $this->context->getInteraction();

        $directive = new ClosureDirective(
            signature: $signature,
            execute: $execute,
            interaction: $interaction,
        );

        $this->context->getClosureRegistry()->register($directive);

        return $directive;
    }

    public function runDirective(string $className, array $arguments = []): DirectiveResponseRecord
    {
        // 1. Chercher dans le registre des closures
        $directive = $this->context->getClosureRegistry()->get($className);

        if ($directive !== null) {
            if ($directive->shouldBootLaravel() && !$this->context->isIntegratedMode() && !$this->context->isInitialized()) {
                $this->initializeIsolatedEnvironment();
            }
            return $this->executeDirectly($directive, $arguments);
        }

        // 2. Chercher dans le registre normal
        $directive = $this->context->getRegistry()->getDirective($className);

        if ($directive !== null) {
            if ($directive->shouldBootLaravel() && !$this->context->isIntegratedMode() && !$this->context->isInitialized()) {
                $this->initializeIsolatedEnvironment();
            }
            return $this->executeDirectly($directive, $arguments);
        }

        // 3. Fallback: utiliser le kernel
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

            if (method_exists($directive, 'setVariadicArguments')) {
                $directive->setVariadicArguments($parsed->variadic_arguments);
            }

            ob_start();
            $bufferStarted = true;
            $exitCode = $directive->execute();
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
