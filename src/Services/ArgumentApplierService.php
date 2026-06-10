<?php
// src/Services/ArgumentApplierService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ExtractedParameterCollection;
use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use InvalidArgumentException;

final class ArgumentApplierService
{
    public function apply(
        ExtractedParameterCollection $parameters,
        array $providedArguments,
        ParsedArgumentCollection $arguments,
        array &$variadicArguments
    ): void {
        $argumentParameters = $parameters->getNonOptions();

        $providedIndex = 0;
        $totalProvided = count($providedArguments);
        $hasVariadic = false;

        foreach ($argumentParameters as $parameter) {
            if ($parameter->isVariadic) {
                $hasVariadic = true;
                $remainingArgs = array_slice($providedArguments, $providedIndex);
                foreach ($remainingArgs as $arg) {
                    $variadicArguments[] = $arg;
                }
                $providedIndex = $totalProvided;
                continue;
            }

            $value = null;

            // Règle 1: Arguments REQUIS
            if ($parameter->required) {
                if ($providedIndex < $totalProvided) {
                    $value = $providedArguments[$providedIndex];
                    $providedIndex++;
                } else {
                    throw new InvalidArgumentException(
                        sprintf('Not enough arguments (missing: "%s")', $parameter->name)
                    );
                }
            }
            // Règle 2: Arguments avec valeur par défaut (peuvent être surchargés)
            elseif ($parameter->default !== null) {
                if ($providedIndex < $totalProvided) {
                    $value = $providedArguments[$providedIndex];
                    $providedIndex++;
                } else {
                    $value = $parameter->default;
                }
            }
            // Règle 3: Arguments optionnels SANS valeur par défaut
            else {
                if ($providedIndex < $totalProvided) {
                    $value = $providedArguments[$providedIndex];
                    $providedIndex++;
                }
                // sinon reste null
            }

            if ($value !== null) {
                $arguments->addArgument($parameter->name, (string) $value);
            }
        }

        if (!$hasVariadic && $providedIndex < $totalProvided) {
            throw new InvalidArgumentException('Too many arguments provided');
        }
    }
}
