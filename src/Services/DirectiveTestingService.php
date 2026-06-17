<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpServices\Services\PrimitiveTypeConverterService;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Throwable;

final class DirectiveTestingService
{
    private PrimitiveTypeConverterService $typeConverter;

    private DirectiveInteractionService $interaction;

    private string $tempDir;

    private ?Application $application;

    private string $originalCwd;

    /**
     * @param  Application|null  $application  L'application Laravel (null si mode isolé)
     */
    public function __construct(?Application $application = null)
    {
        $this->application = $application;
        $this->typeConverter = new PrimitiveTypeConverterService;
        $this->interaction = new DirectiveInteractionService(new RenderDispatcher, new InputDispatcher);

        // Sauvegarder le répertoire original AVANT de créer le temporaire
        $this->originalCwd = getcwd();

        $this->setupTempDirectory();
    }

    /**
     * Exécute une directive par sa classe.
     */
    public function run(string $class, array $arguments = []): DirectiveResponseRecord
    {
        $directive = $this->createDirective($class);

        return $this->executeDirective($directive, $arguments);
    }

    /**
     * Nettoie l'environnement de test.
     */
    public function destroy(): void
    {
        // Restaurer le répertoire original AVANT de supprimer le temporaire
        if ($this->originalCwd !== '' && is_dir($this->originalCwd)) {
            chdir($this->originalCwd);
        }

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function getInteraction(): DirectiveInteractionService
    {
        return $this->interaction;
    }

    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    private function createDirective(string $class): AbstractDirective
    {
        if (! class_exists($class)) {
            throw new InvalidArgumentException("Directive class {$class} does not exist");
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $paramType = $param->getType();
            $paramName = $paramType?->getName();

            $args[] = match (true) {
                $paramName === DirectiveContext::class => $this->createDirectiveContext($class),
                $paramName === DirectiveInteractionService::class => $this->interaction,
                $this->application !== null && $paramType?->isBuiltin() === false && class_exists($paramName) => $this->application->make($paramName),
                default => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            };
        }

        return $reflection->newInstanceArgs($args);
    }

    private function createDirectiveContext(string $class): DirectiveContext
    {
        $blueprint = new DirectiveBlueprintRecord($class, '', '');
        $aliases = new StringTypedCollection;

        return new DirectiveContext(
            blueprint: $blueprint,
            aliases: $aliases,
            laravelApplication: $this->application,
        );
    }

    private function executeDirective(AbstractDirective $directive, array $arguments): DirectiveResponseRecord
    {
        $context = $this->createDirectiveContext(get_class($directive));
        $parsed = $this->parseArguments($directive->getSignature(), $arguments);

        $context->setArguments($parsed['arguments']);
        $context->setOptions($parsed['options']);
        $context->setVariadicArguments($parsed['variadic']);

        $hydratedDirective = $this->hydrateDirective($directive, $context);

        ob_start();
        try {
            $exitCode = $hydratedDirective->execute();
            $output = ob_get_clean();

            return new DirectiveResponseRecord($exitCode, $output);
        } catch (Throwable $e) {
            ob_end_clean();

            return new DirectiveResponseRecord(ExitCode::FAILURE, $e->getMessage());
        }
    }

    private function parseArguments(string $signature, array $arguments): array
    {
        $parser = new DirectiveParserService;
        $argumentCollection = new StringTypedCollection;

        foreach ($arguments as $arg) {
            $argumentCollection->add((string) $arg);
        }

        $parsed = $parser->parse($signature, $argumentCollection);

        $argumentsVO = new ParameterVOCollection;
        foreach ($parsed->arguments as $arg) {
            $type = $this->typeConverter->detectType($arg->value);
            $value = $this->typeConverter->convert($arg->value, $type);
            $argumentsVO->add(new ParameterVO($arg->name, $value, $type));
        }

        $optionsVO = new ParameterVOCollection;
        foreach ($parsed->options as $opt) {
            $value = match ($opt->value) {
                'true' => true,
                'false' => false,
                default => $opt->value,
            };
            $type = $this->typeConverter->detectType($value);
            $optionsVO->add(new ParameterVO($opt->name, $value, $type));
        }

        return [
            'arguments' => $argumentsVO,
            'options' => $optionsVO,
            'variadic' => $parsed->variadic_arguments,
        ];
    }

    private function hydrateDirective(AbstractDirective $directive, DirectiveContext $context): AbstractDirective
    {
        $reflection = new \ReflectionClass($directive);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $directive;
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $paramName = $param->getType()?->getName();

            $args[] = match (true) {
                $paramName === DirectiveContext::class => $context,
                $paramName === DirectiveInteractionService::class => $this->interaction,
                default => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            };
        }

        return $reflection->newInstanceArgs($args);
    }

    private function setupTempDirectory(): void
    {
        $this->tempDir = sys_get_temp_dir().'/directive_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        chdir($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
