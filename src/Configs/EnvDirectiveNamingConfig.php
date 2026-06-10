<?php

// src/Configs/EnvDirectiveNamingConfig.php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\Directive\Contracts\Configs\DirectiveNamingConfigInterface;

final class EnvDirectiveNamingConfig implements DirectiveNamingConfigInterface
{
    public function classSuffix(): string
    {
        return getenv('DIRECTIVE_CLASS_SUFFIX') ?: 'Directive';
    }

    public function optionPlaceholder(): string
    {
        return getenv('DIRECTIVE_OPTION_PLACEHOLDER') ?: '{--option}';
    }

    public function defaultDescriptionTemplate(): string
    {
        return getenv('DIRECTIVE_DESCRIPTION_TEMPLATE') ?: 'Generated directive for {{signature}}';
    }

    public function dateFormat(): string
    {
        return getenv('DIRECTIVE_DATE_FORMAT') ?: 'Y-m-d H:i:s';
    }
}
