<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Contracts\DirectiveRegistrarInterface;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * @deprecated Depuis la version 7.0.0, sera supprimée dans la version 10.0.0
 * 
 * Cette classe est remplacée par la découverte automatique via le dossier src/Directives/
 * 
 * MIGRATION :
 * - Avant : Les packages devaient appeler $registrar->register() dans leur ServiceProvider
 * 
 * Plus aucune action n'est requise de la part des packages. La découverte est automatique.
 * 
 * @see DirectiveDiscoveryService::discoverFromVendorPackages()
 */
class DirectiveRegistrar implements DirectiveRegistrarInterface
{
    private StringTypedCollection $registeredDirectives;

    private array $signatureMap = [];

    private array $aliasMap = [];

    private array $directivesMetadata = [];

    public function __construct()
    {
        // Émettre un warning de dépréciation uniquement en mode développement
        if (getenv('APP_ENV') === 'local' || getenv('APP_DEBUG') === 'true') {
            trigger_error(
                'DirectiveRegistrar is deprecated since version 7.0.0 and will be removed in version 10.0.0. ' .
                    'Use automatic discovery via src/Directives/ folder instead.',
                E_USER_DEPRECATED
            );
        }

        $this->registeredDirectives = new StringTypedCollection;
    }

    public function register(StringTypedCollection $directiveClasses): self
    {
        foreach ($directiveClasses as $class) {
            if (! is_string($class)) {
                continue;
            }

            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, DirectiveInterface::class)) {
                continue;
            }

            if (! $this->registeredDirectives->contains($class)) {
                $this->registeredDirectives->add($class);
                $this->buildMaps($class);
            }
        }

        return $this;
    }

    private function buildMaps(string $class): void
    {
        $reflection = new \ReflectionClass($class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $signature = $instance->getSignature();

        // Store by full signature
        if (! isset($this->signatureMap[$signature])) {
            $this->signatureMap[$signature] = new StringTypedCollection;
        }
        $this->signatureMap[$signature]->add($class);

        // Also store by command name (without arguments)
        $commandName = explode(' ', $signature)[0];
        if (! isset($this->signatureMap[$commandName])) {
            $this->signatureMap[$commandName] = new StringTypedCollection;
        }
        if (! $this->signatureMap[$commandName]->contains($class)) {
            $this->signatureMap[$commandName]->add($class);
        }

        // Store metadata
        $this->directivesMetadata[$commandName] = (object) [
            'signature' => $commandName,
            'fullSignature' => $signature,
            'class' => $class,
            'description' => $instance->getDescription(),
            'aliases' => $instance->getAliases(),
        ];

        $aliases = $instance->getAliases();
        foreach ($aliases as $alias) {
            if (! isset($this->aliasMap[$alias])) {
                $this->aliasMap[$alias] = new StringTypedCollection;
            }
            $this->aliasMap[$alias]->add($class);
        }
    }

    public function find(string $name): StringTypedCollection
    {
        if (isset($this->aliasMap[$name])) {
            return $this->aliasMap[$name];
        }

        if (isset($this->signatureMap[$name])) {
            return $this->signatureMap[$name];
        }

        return new StringTypedCollection;
    }

    public function getAllDirectivesMetadata(): TypedCollection
    {
        $collection = new TypedCollection(\stdClass::class);
        foreach ($this->directivesMetadata as $metadata) {
            $collection->add($metadata);
        }

        return $collection;
    }

    public function hasConflict(string $name): bool
    {
        $matches = $this->find($name);

        return $matches->count() > 1;
    }

    public function getSignatureMap(): array
    {
        return $this->signatureMap;
    }

    public function getAliasMap(): array
    {
        return $this->aliasMap;
    }

    public function getRegistered(): StringTypedCollection
    {
        return $this->registeredDirectives;
    }

    public function isRegistered(string $directiveClass): bool
    {
        return $this->registeredDirectives->contains($directiveClass);
    }

    public function clear(): self
    {
        $this->registeredDirectives = new StringTypedCollection;
        $this->signatureMap = [];
        $this->aliasMap = [];
        $this->directivesMetadata = [];

        return $this;
    }

    public function count(): int
    {
        return $this->registeredDirectives->count();
    }
}
