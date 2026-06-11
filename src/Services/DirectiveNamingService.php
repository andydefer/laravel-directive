<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Configs\DirectiveNamingConfigInterface;

/**
 * Service for generating directive class names and signatures.
 *
 * Provides naming conventions and stub replacement functionality for
 * automatic directive code generation. Converts between naming conventions
 * (kebab-case to PascalCase) and generates standard signatures.
 */
class DirectiveNamingService
{
    public function __construct(
        private readonly DirectiveNamingConfigInterface $config,
    ) {}

    /**
     * Generate class name from directive name.
     *
     * Converts kebab-case to PascalCase and appends suffix.
     *
     * @param  string  $name  The directive name in kebab-case (e.g., 'user-create')
     * @return string Generated class name in PascalCase with suffix
     *
     * @example
     * 'user-create'    -> 'UserCreateDirective'
     * 'clean-log'      -> 'CleanLogDirective'
     * 'db-migrate-fresh' -> 'DbMigrateFreshDirective'
     * 'api-v2'         -> 'ApiV2Directive'
     */
    public function generateClassName(string $name): string
    {
        $parts = explode('-', $name);
        $className = '';

        foreach ($parts as $part) {
            $className .= $this->capitalizeSegment($part);
        }

        return $className . $this->config->classSuffix();
    }

    /**
     * Generate signature with placeholder option.
     *
     * @param  string  $name  Base directive name
     * @return string Full signature with option placeholder
     */
    public function generateSignatureWithOption(string $name): string
    {
        return $name . ' ' . $this->config->optionPlaceholder();
    }

    /**
     * Replace variables in stub template.
     *
     * Available placeholders:
     * - {{class}}     : Directive class name
     * - {{signature}} : Directive signature with option placeholder
     * - {{description}}: Directive description
     * - {{date}}      : Current date and time
     *
     * @param  string  $stub  The stub template content
     * @param  string  $className  The directive class name
     * @param  string  $signature  The directive signature
     * @return string Processed content with placeholders replaced
     */
    public function replaceStubVariables(string $stub, string $className, string $signature): string
    {
        $replacements = $this->buildReplacementMap($className, $signature);

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );
    }

    /**
     * Extract base name from class name.
     *
     * Removes the suffix and converts PascalCase to kebab-case.
     *
     * @param  string  $className  The directive class name (e.g., 'UserCreateDirective')
     * @return string Base directive name in kebab-case
     *
     * @example
     * 'UserCreateDirective' -> 'user-create'
     * 'ListDirective'       -> 'list'
     * 'ApiV2Directive'      -> 'api-v2'
     */
    public function extractBaseName(string $className): string
    {
        $suffix = $this->config->classSuffix();

        if (!str_ends_with($className, $suffix)) {
            return $this->convertToKebabCase($className);
        }

        $withoutSuffix = substr($className, 0, -strlen($suffix));

        return $this->convertToKebabCase($withoutSuffix);
    }

    /**
     * Generate complete stub content from template.
     *
     * @param  string  $template  The stub template
     * @param  string  $className  The directive class name
     * @param  string  $signature  The directive signature
     * @param  string  $description  Optional custom description
     * @return string Complete directive stub
     */
    public function generateStub(
        string $template,
        string $className,
        string $signature,
        string $description = ''
    ): string {
        return $this->replaceStubVariables($template, $className, $signature);
    }

    /**
     * Capitalize a single segment (first letter uppercase, rest lowercase).
     */
    private function capitalizeSegment(string $segment): string
    {
        return ucfirst(strtolower($segment));
    }

    /**
     * Convert PascalCase to kebab-case.
     */
    private function convertToKebabCase(string $input): string
    {
        $pattern = '/(?<!^)(?=[A-Z])/';
        $parts = preg_split($pattern, $input);

        if ($parts === false) {
            return strtolower($input);
        }

        $lowercaseParts = array_map('strtolower', $parts);

        return implode('-', $lowercaseParts);
    }

    /**
     * Build replacement map for stub variables.
     *
     * @return array<string, string> Associative array of placeholders to values
     */
    private function buildReplacementMap(string $className, string $signature): array
    {
        return [
            '{{class}}' => $className,
            '{{signature}}' => $this->generateSignatureWithOption($signature),
            '{{description}}' => $this->getDefaultDescription($signature),
            '{{date}}' => $this->getCurrentDateTime(),
        ];
    }

    /**
     * Get default description for a directive.
     */
    private function getDefaultDescription(string $signature): string
    {
        $template = $this->config->defaultDescriptionTemplate();
        return str_replace('{{signature}}', $signature, $template);
    }

    /**
     * Get current date and time for stub generation.
     */
    private function getCurrentDateTime(): string
    {
        return date($this->config->dateFormat());
    }
}
