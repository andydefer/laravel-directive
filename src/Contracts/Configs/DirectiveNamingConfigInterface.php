<?php

// src/Contracts/Configs/DirectiveNamingConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Configs;

interface DirectiveNamingConfigInterface
{
    /**
     * Get the suffix appended to all directive class names.
     *
     * @return string Class suffix (e.g., 'Directive')
     */
    public function classSuffix(): string;

    /**
     * Get the placeholder for option in generated signatures.
     *
     * @return string Option placeholder (e.g., '{--option}')
     */
    public function optionPlaceholder(): string;

    /**
     * Get the default description template.
     *
     * @return string Description template with {{signature}} placeholder
     */
    public function defaultDescriptionTemplate(): string;

    /**
     * Get the date format for stub generation.
     *
     * @return string PHP date format (e.g., 'Y-m-d H:i:s')
     */
    public function dateFormat(): string;
}
