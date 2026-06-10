<?php

// src/Services/ArgumentSplitterService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class ArgumentSplitterService
{
    private const VARIADIC_START = '[';

    private const VARIADIC_END = ']';

    private const VARIADIC_SEPARATOR = ',';

    public function split(StringTypedCollection $argv): array
    {
        $regular = [];
        $variadic = [];
        $inVariadic = false;
        $variadicContent = '';

        foreach ($argv as $item) {
            if ($item === self::VARIADIC_START) {
                $inVariadic = true;

                continue;
            }

            if ($inVariadic && $item === self::VARIADIC_END) {
                $inVariadic = false;
                // Extraire les éléments séparés par des virgules
                $items = explode(self::VARIADIC_SEPARATOR, $variadicContent);
                foreach ($items as $variadicItem) {
                    $trimmed = trim($variadicItem);
                    if ($trimmed !== '') {
                        $variadic[] = $trimmed;
                    }
                }
                $variadicContent = '';

                continue;
            }

            if ($inVariadic) {
                // Accumuler le contenu variadique
                if ($variadicContent !== '') {
                    $variadicContent .= ' ';
                }
                $variadicContent .= $item;
            } else {
                $regular[] = $item;
            }
        }

        return [
            'regular' => $regular,
            'variadic' => $variadic,
        ];
    }
}
