<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Testing\ClosureDirective;

interface DirectiveTestingServiceInterface
{
    /**
     * Enregistre une directive par son nom de classe.
     *
     * @param string $class Le FQCN de la directive
     * @throws \InvalidArgumentException Si la classe n'existe pas
     */
    public function registerDirective(string $class): void;

    /**
     * Enregistre une instance de directive directement.
     *
     * @param AbstractDirective $directive L'instance de directive
     */
    public function registerDirectiveInstance(AbstractDirective $directive): void;

    /**
     * Enregistre plusieurs directives par leurs noms de classe.
     *
     * @param array<string> $classes Les FQCN des directives
     */
    public function registerDirectives(array $classes): void;

    /**
     * Enregistre plusieurs instances de directives.
     *
     * @param array<AbstractDirective> $directives Les instances de directives
     */
    public function registerDirectiveInstances(array $directives): void;

    /**
     * Enregistre une directive et l'exécute immédiatement.
     *
     * @param string $class Le FQCN de la directive
     * @param array<string> $arguments Les arguments à passer
     * @return DirectiveResponseRecord La réponse
     */
    public function registerAndRun(string $class, array $arguments = []): DirectiveResponseRecord;

    /**
     * Enregistre une instance de directive et l'exécute immédiatement.
     *
     * @param AbstractDirective $directive L'instance de directive
     * @param array<string> $arguments Les arguments à passer
     * @return DirectiveResponseRecord La réponse
     */
    public function registerAndRunInstance(AbstractDirective $directive, array $arguments = []): DirectiveResponseRecord;

    /**
     * Exécute une directive en l'enregistrant automatiquement.
     *
     * @param string $class Le FQCN de la directive
     * @param array<string> $arguments Les arguments à passer
     * @return DirectiveResponseRecord La réponse
     */
    public function run(string $class, array $arguments = []): DirectiveResponseRecord;

    /**
     * Exécute une directive déjà enregistrée par sa signature.
     *
     * @param string $signature La signature de la directive
     * @param array<string> $arguments Les arguments à passer
     * @return DirectiveResponseRecord La réponse
     */
    public function runDirective(string $signature, array $arguments = []): DirectiveResponseRecord;

    /**
     * Crée une directive temporaire avec une closure.
     *
     * @param string $signature La signature de la directive
     * @param callable $execute La logique d'exécution
     * @return ClosureDirective La directive créée
     */
    public function createTestDirective(string $signature, callable $execute): ClosureDirective;

    /**
     * Supprime toutes les directives enregistrées.
     */
    public function clearRegisteredDirectives(): void;

    /**
     * Détruit l'environnement de test.
     */
    public function destroy(): void;

    /**
     * Retourne le service d'interaction.
     *
     * @return DirectiveInteractionService
     */
    public function getInteraction(): DirectiveInteractionService;

    /**
     * Retourne le contexte de test.
     *
     * @return DirectiveTestingContext
     */
    public function getContext(): DirectiveTestingContext;
}
