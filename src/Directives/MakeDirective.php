<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class MakeDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'make:directive {name}';
    }

    public function getDescription(): string
    {
        return 'Create a new directive class';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        $aliases->add('create:directive');
        $aliases->add('make:cmd');
        return $aliases;
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        if ($name === null) {
            $this->error('Directive name is required');
            $this->line('Usage: directive make:directive <name>');
            $this->line('Example: directive make:directive user:list');
            return ExitCode::INVALID_ARGUMENT;
        }

        $className = $this->generateClassName($name);
        $filePath = $this->getDirectivePath($className);

        if (file_exists($filePath)) {
            $this->error("Directive already exists: {$filePath}");
            return ExitCode::FAILURE;
        }

        if (!$this->createDirectiveDirectory()) {
            $this->error('Cannot create directives directory');
            return ExitCode::FAILURE;
        }

        if (!$this->createDirectiveFile($filePath, $className, $name)) {
            $this->error('Cannot create directive file');
            return ExitCode::FAILURE;
        }

        $this->info('✅ Directive created successfully!');
        $this->line("   Class: {$className}");
        $this->line("   Path: {$filePath}");
        $this->line("   Signature: {$this->generateSignature($name)}");

        return ExitCode::SUCCESS;
    }

    /**
     * Generate class name from signature.
     *
     * Example: user:list -> UserListDirective
     *
     * @param string $name Directive signature
     *
     * @return string Generated class name
     */
    private function generateClassName(string $name): string
    {
        $parts = explode(':', $name);
        $className = '';

        foreach ($parts as $part) {
            $className .= str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $part)));
        }

        return $className . 'Directive';
    }

    /**
     * Generate signature with placeholder option.
     *
     * @param string $name Base signature
     *
     * @return string Full signature
     */
    private function generateSignature(string $name): string
    {
        return $name . ' {--option}';
    }

    /**
     * Get the file path for a directive.
     *
     * @param string $className Directive class name
     *
     * @return string Full file path
     */
    private function getDirectivePath(string $className): string
    {
        return getcwd() . '/app/Directives/' . $className . '.php';
    }

    /**
     * Create the directives directory if it doesn't exist.
     *
     * @return bool True if directory exists or was created
     */
    private function createDirectiveDirectory(): bool
    {
        $dir = getcwd() . '/app/Directives';

        if (is_dir($dir)) {
            return true;
        }

        if (mkdir($dir, 0755, true)) {
            $this->line("📁 Created directory: app/Directives/");
            return true;
        }

        return false;
    }

    /**
     * Create the directive file from stub.
     *
     * @param string $path      File path
     * @param string $className Directive class name
     * @param string $signature Directive signature
     *
     * @return bool True if file was created
     */
    private function createDirectiveFile(string $path, string $className, string $signature): bool
    {
        $stub = $this->getStub();
        $content = str_replace(
            ['{{class}}', '{{signature}}', '{{description}}', '{{date}}'],
            [
                $className,
                $this->generateSignature($signature),
                "Generated directive for {$signature}",
                date('Y-m-d H:i:s'),
            ],
            $stub
        );

        return file_put_contents($path, $content) !== false;
    }

    /**
     * Get the directive stub template.
     *
     * @return string Stub content
     */
    private function getStub(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * Generated directive for {{signature}}
 * Created at: {{date}}
 */
final class {{class}} extends AbstractDirective
{
    public function getSignature(): string
    {
        return '{{signature}}';
    }

    public function getDescription(): string
    {
        return '{{description}}';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        // $aliases->add('your-alias');
        return $aliases;
    }

    public function execute(): ExitCode
    {
        // TODO: Implement your directive logic here

        $this->info('Directive executed successfully!');

        return ExitCode::SUCCESS;
    }
}
PHP;
    }
}
