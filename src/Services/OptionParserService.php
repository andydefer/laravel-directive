<?php
// src/Services/OptionParserService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Configs\DirectiveParserConfig;
use AndyDefer\Directive\Contracts\Configs\DirectiveParserConfigInterface;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class OptionParserService
{
    private DirectiveParserConfigInterface $config;

    public function __construct(?DirectiveParserConfigInterface $config = null)
    {
        $this->config = $config ?? new DirectiveParserConfig();
    }

    public function parseOptions(StringTypedCollection $argv, ParsedOptionCollection $options): void
    {

        foreach ($argv as $argument) {
            if ($this->isLongOption($argument)) {
                $this->parseLongOption($argument, $options);
            } elseif ($this->isShortOption($argument)) {
                $this->parseShortOption($argument, $options);
            }
        }
    }

    public function isOption(string $argument): bool
    {
        return $this->isLongOption($argument) || $this->isShortOption($argument);
    }

    private function isLongOption(string $argument): bool
    {
        return str_starts_with($argument, $this->config->longOptionPrefix());
    }

    private function isShortOption(string $argument): bool
    {
        return str_starts_with($argument, $this->config->shortOptionPrefix())
            && !str_starts_with($argument, $this->config->longOptionPrefix())
            && strlen($argument) > 1;
    }

    private function parseLongOption(string $argument, ParsedOptionCollection $options): void
    {
        $prefixLength = strlen($this->config->longOptionPrefix());
        $withoutPrefix = substr($argument, $prefixLength);

        $separatorPos = strpos($withoutPrefix, $this->config->optionValueSeparator());

        if ($separatorPos === false) {
            // Flag option: --force
            $name = $withoutPrefix;
            $options->addOption($name, $this->config->trueValue(), true);
        } else {
            // Option with value: --role=admin or --message=Hello=World
            $name = substr($withoutPrefix, 0, $separatorPos);
            $value = substr($withoutPrefix, $separatorPos + 1);

            if ($value === '') {
                // --role=
                $options->addOption($name, $this->config->trueValue(), true);
            } else {
                // --role=admin or --message=Hello=World
                $options->addOption($name, $value, false);
            }
        }
    }

    private function parseShortOption(string $argument, ParsedOptionCollection $options): void
    {
        $option = substr($argument, strlen($this->config->shortOptionPrefix()));

        if (strlen($option) > 1) {
            $characters = str_split($option);
            foreach ($characters as $character) {
                $options->addOption($character, $this->config->trueValue(), true);
            }
        } else {
            $options->addOption($option, $this->config->trueValue(), true);
        }
    }
}
