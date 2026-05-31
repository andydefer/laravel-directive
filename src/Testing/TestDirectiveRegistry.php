<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\DirectiveLoaderInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Directive\Services\SignatureValidationService;

final class TestDirectiveRegistry implements DirectiveLoaderInterface
{
    /** @var array<string, AbstractDirective> */
    private array $directives = [];

    private ?DirectiveInteractionService $interaction = null;

    private ?SignatureValidationService $signatureValidator = null;

    private ?DirectiveNamingService $namingService = null;

    private ?LaravelBootstrapper $laravelBootstrapper = null;

    public function setInteraction(DirectiveInteractionService $interaction): void
    {
        $this->interaction = $interaction;
    }

    public function setSignatureValidator(SignatureValidationService $signatureValidator): void
    {
        $this->signatureValidator = $signatureValidator;
    }

    public function setNamingService(DirectiveNamingService $namingService): void
    {
        $this->namingService = $namingService;
    }

    public function setLaravelBootstrapper(LaravelBootstrapper $laravelBootstrapper): void
    {
        $this->laravelBootstrapper = $laravelBootstrapper;
    }

    public function register(string $signature, AbstractDirective $directive): void
    {
        $this->directives[$signature] = $directive;

        foreach ($directive->getAliases() as $alias) {
            $this->directives[$alias] = $directive;
        }
    }

    public function registerByClass(string $className, array $constructorArgs = []): AbstractDirective
    {
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (empty($constructorArgs) && $constructor) {
            $params = $constructor->getParameters();
            foreach ($params as $param) {
                $paramType = $param->getType();
                $paramName = $param->getName();

                if ($paramType && $paramType->getName() === DirectiveInteractionService::class) {
                    if ($this->interaction === null) {
                        throw new \RuntimeException('DirectiveInteractionService not set. Call setInteraction() first.');
                    }
                    $constructorArgs[] = $this->interaction;
                } elseif ($paramType && $paramType->getName() === SignatureValidationService::class) {
                    if ($this->signatureValidator === null) {
                        $this->signatureValidator = new SignatureValidationService;
                    }
                    $constructorArgs[] = $this->signatureValidator;
                } elseif ($paramType && $paramType->getName() === DirectiveNamingService::class) {
                    if ($this->namingService === null) {
                        $this->namingService = new DirectiveNamingService;
                    }
                    $constructorArgs[] = $this->namingService;
                } elseif ($paramType && $paramType->getName() === LaravelBootstrapper::class) {
                    if ($this->laravelBootstrapper === null) {
                        $this->laravelBootstrapper = new LaravelBootstrapper;
                    }
                    $constructorArgs[] = $this->laravelBootstrapper;
                } elseif ($paramName === 'stubPath') {
                    $constructorArgs[] = null;
                } else {
                    $constructorArgs[] = null;
                }
            }
        }

        $directive = $reflection->newInstanceArgs($constructorArgs);
        $this->register($directive->getSignature(), $directive);

        return $directive;
    }

    public function load(): DirectiveMetadataCollection
    {
        $results = new DirectiveMetadataCollection;

        foreach ($this->directives as $signature => $directive) {
            $results->add(new DirectiveMetadataRecord(
                signature: $signature,
                class: get_class($directive),
                description: $directive->getDescription(),
                aliases: $directive->getAliases(),
            ));
        }

        return $results;
    }

    public function getDirective(string $signature): ?AbstractDirective
    {
        return $this->directives[$signature] ?? null;
    }

    public function clear(): void
    {
        $this->directives = [];
    }
}
