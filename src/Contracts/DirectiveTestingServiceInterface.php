<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Records\DirectiveResponseRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;

interface DirectiveTestingServiceInterface
{
    /**
     * Enregistre une directive par son nom de classe.
     */
    public function registerDirective(string $class): void;

    /**
     * Enregistre plusieurs directives par leurs noms de classe.
     */
    public function registerDirectives(array $classes): void;

    /**
     * Exécute une directive en l'enregistrant automatiquement.
     */
    public function run(string $class, array $arguments = []): DirectiveResponseRecord;

    /**
     * Exécute une directive déjà enregistrée par sa signature.
     */
    public function runDirective(string $signature, array $arguments = []): DirectiveResponseRecord;

    /**
     * Supprime toutes les directives enregistrées et détruit l'environnement.
     */
    public function destroy(): void;

    /**
     * Retourne le service d'interaction.
     */
    public function getInteraction(): DirectiveInteractionService;
}
