<?php

// src/Services/ArgumentSplitterService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Configs\DirectiveParserConfigInterface;
use AndyDefer\Directive\Records\ArgumentSplitResultRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

class ArgumentSplitterService
{
    public function __construct(
        private readonly DirectiveParserConfigInterface $config,
    ) {}

    public function split(StringTypedCollection $argv): ArgumentSplitResultRecord
    {
        $regular = [];
        $variadic = [];
        $inVariadic = false;
        $variadicContent = '';

        foreach ($argv as $item) {
            if ($item === $this->config->variadicStart()) {
                $inVariadic = true;
                continue;
            }

            if ($inVariadic && $item === $this->config->variadicEnd()) {
                $inVariadic = false;
                $items = explode($this->config->variadicSeparator(), $variadicContent);
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
                if ($variadicContent !== '') {
                    $variadicContent .= ' ';
                }
                $variadicContent .= $item;
            } else {
                $regular[] = $item;
            }
        }

        $regularCollection = new StringTypedCollection;
        foreach ($regular as $item) {
            $regularCollection->add($item);
        }

        $variadicCollection = new StringTypedCollection;
        foreach ($variadic as $item) {
            $variadicCollection->add($item);
        }

        return new ArgumentSplitResultRecord(
            regular: $regularCollection,
            variadic: $variadicCollection,
        );
    }
}
