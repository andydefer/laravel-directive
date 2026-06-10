<?php
// src/Services/DirectiveTestingService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use AndyDefer\Directive\Steps\BootstrapLaravelStep;
use AndyDefer\Directive\Steps\BuildContainerStep;
use AndyDefer\Directive\Steps\ChangeToTempDirectoryStep;
use AndyDefer\Directive\Steps\CreateLaravelStructureStep;
use AndyDefer\Directive\Steps\CreateTempDirectoryStep;
use AndyDefer\Directive\Steps\DirectiveTestingStepInterface;
use AndyDefer\Directive\Testing\ClosureDirective;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use InvalidArgumentException;

final class DirectiveTestingService
{
    /**
     * @var array<DirectiveTestingStepInterface>
     */
    private array $steps = [];

    public function __construct(
        private readonly DirectiveTestingContext $context,
        ?DirectiveTestingConfigInterface $config = null,
    ) {
        $this->context->setConfig($config ?? new DirectiveTestingConfig());
        $this->initializeSteps();
        $this->executeChain();
    }

    private function initializeSteps(): void
    {
        $this->steps = [
            new CreateTempDirectoryStep(),
            new ChangeToTempDirectoryStep(),
            new CreateLaravelStructureStep(),
            new BootstrapLaravelStep(),
            new BuildContainerStep(),
        ];
    }

    private function executeChain(): void
    {
        if ($this->context->isInitialized()) {
            return;
        }

        $chain = $this->buildChain($this->steps);
        $chain($this->context);
        $this->context->setInitialized(true);
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

    /**
     * Register a directive for testing.
     *
     * @param AbstractDirective $directive The directive to register
     */
    public function registerDirective(AbstractDirective $directive): void
    {
        $this->context->getRegistry()->register($directive);
    }

    /**
     * Register multiple directives for testing.
     *
     * @param array<AbstractDirective> $directives The directives to register
     */
    public function registerDirectives(array $directives): void
    {
        foreach ($directives as $directive) {
            $this->registerDirective($directive);
        }
    }

    /**
     * Clear all registered directives.
     */
    public function clearRegisteredDirectives(): void
    {
        $this->context->getRegistry()->clear();
    }

    /**
     * Create a temporary test directive with a closure as execution logic.
     *
     * @param string $signature The directive signature
     * @param callable $execute The execution logic
     *
     * @return ClosureDirective The created directive
     */
    public function createTestDirective(string $signature, callable $execute): ClosureDirective
    {
        $interaction = $this->context->getInteraction();

        $directive = new ClosureDirective(
            signature: $signature,
            execute: $execute,
            interaction: $interaction,
        );

        // ✅ Enregistrer dans le registre des closures, pas dans le registre principal
        $this->context->getClosureRegistry()->register($directive);

        return $directive;
    }

    /**
     * Run a directive by its class name.
     *
     * @param string $className FQCN of the directive
     * @param array<string> $arguments The arguments to pass
     *
     * @return DirectiveResponseRecord The response containing exit code and output
     */
    public function runDirective(string $className, array $arguments = []): DirectiveResponseRecord
    {
        // 1. Chercher d'abord dans le registre des closures
        $directive = $this->context->getClosureRegistry()->get($className);

        if ($directive !== null) {
            return $this->executeDirectly($directive, $arguments);
        }

        // 2. Chercher dans le registre normal (par FQCN)
        $directive = $this->context->getRegistry()->getDirective($className);

        if ($directive !== null) {
            return $this->executeDirectly($directive, $arguments);
        }

        // 3. Fallback: try via the kernel with the signature
        $argv = array_merge(['directive', $className], $arguments);

        ob_start();
        $exitCode = $this->context->getKernel()->run($argv);
        $output = ob_get_clean();

        $this->context->addExecutedDirective($className);

        return new DirectiveResponseRecord(
            exitCode: $exitCode,
            output: $output,
        );
    }

    /**
     * Execute a directive directly without going through the kernel.
     *
     * @param AbstractDirective $directive The directive instance
     * @param array<string> $arguments The arguments to pass
     *
     * @return DirectiveResponseRecord The response
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

    /**
     * Get the interaction service.
     *
     * @return DirectiveInteractionService
     */
    public function getInteraction()
    {
        return $this->context->getInteraction();
    }

    /**
     * Get the context.
     *
     * @return DirectiveTestingContext
     */
    public function getContext(): DirectiveTestingContext
    {
        return $this->context;
    }

    /**
     * Clean up the testing environment.
     */
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

    /**
     * Recursively remove a directory.
     *
     * @param string $dir The directory to remove
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
