<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

/**
 * Service for generating directive class names and signatures.
 */
class DirectiveNamingService
{
    /**
     * Generate class name from directive name.
     *
     * Converts kebab-case to PascalCase and appends 'Directive'.
     *
     * Examples:
     * - user-create -> UserCreateDirective
     * - clean-log -> CleanLogDirective
     * - db-migrate-fresh -> DbMigrateFreshDirective
     * - api-v2 -> ApiV2Directive
     *
     * @param  string  $name  The directive name (e.g., 'user-create')
     * @return string Generated class name
     */
    public function generateClassName(string $name): string
    {
        $parts = explode('-', $name);
        $className = '';

        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return $className.'Directive';
    }

    /**
     * Generate signature with placeholder option.
     *
     * @param  string  $name  Base directive name
     * @return string Full signature with option placeholder
     */
    public function generateSignatureWithOption(string $name): string
    {
        return $name.' {--option}';
    }

    /**
     * Replace variables in stub template.
     *
     * @param  string  $stub  The stub template content
     * @param  string  $className  The directive class name
     * @param  string  $signature  The directive signature
     * @return string Processed content
     */
    public function replaceStubVariables(string $stub, string $className, string $signature): string
    {
        return str_replace(
            ['{{class}}', '{{signature}}', '{{description}}', '{{date}}'],
            [
                $className,
                $this->generateSignatureWithOption($signature),
                "Generated directive for {$signature}",
                date('Y-m-d H:i:s'),
            ],
            $stub
        );
    }
}
