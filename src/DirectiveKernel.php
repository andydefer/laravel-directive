<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

class DirectiveKernel
{
    private bool $debug = false;

    public function __construct(
        private readonly DirectiveExecutionService $service,
    ) {}

    public function enableDebug(bool $debug = true): self
    {
        $this->debug = $debug;
        return $this;
    }

    public function run(array $argv): ExitCode
    {
        // Si aucun argument n'est passé, afficher l'aide par défaut
        if (count($argv) < 2) {
            return $this->showDefaultHelp();
        }

        $signature = $argv[1];

        // Si la signature commence par '--', c'est une option globale
        if (str_starts_with($signature, '--')) {
            return $this->service->execute(new DirectiveExecutionRecord(
                signature: $signature,
                arguments: new StringTypedCollection(),
            ));
        }

        $args = array_slice($argv, 2);

        $arguments = new StringTypedCollection();

        foreach ($args as $arg) {
            $arguments->add($arg);
        }

        $result = $this->service->execute(new DirectiveExecutionRecord(
            signature: $signature,
            arguments: $arguments,
        ));


        return $result;
    }

    /**
     * Affiche l'aide par défaut quand aucune directive n'est spécifiée.
     */
    private function showDefaultHelp(): ExitCode
    {
        return $this->service->execute(new DirectiveExecutionRecord(
            signature: '--help',
            arguments: new StringTypedCollection(),
        ));
    }
}
