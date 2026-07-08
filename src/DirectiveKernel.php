<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveHydratorService;

final class DirectiveKernel
{
    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveHydratorService $hydrator,
    ) {}

    public function run(array $argv): ExitCode
    {
        if (count($argv) < 2) {
            return $this->executeDirective('help', 'help');
        }

        $query = implode(' ', array_slice($argv, 1));

        $parts = explode(' ', $query);
        $commandName = $parts[0];

        return $this->executeDirective($commandName, $query);
    }

    private function executeDirective(string $commandName, string $query): ExitCode
    {
        $directives = $this->discovery->discover();

        $directive = null;
        foreach ($directives as $d) {
            $signatureParts = explode(' ', $d->signature);
            $directiveName = $signatureParts[0];

            if ($directiveName === $commandName) {
                $directive = $d;
                break;
            }

            foreach ($d->aliases as $alias) {
                if ($alias === $commandName) {
                    $directive = $d;
                    break 2;
                }
            }
        }

        if ($directive === null) {
            return ExitCode::NOT_FOUND;
        }

        $instance = $this->hydrator->hydrate($directive->class, $query);

        return $instance->run();
    }
}
