<?php

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Traits\FileCreator;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * Fixture class that implements the trait and extends AbstractDirective
 */
class FileCreatorTestDirective extends AbstractDirective
{
    use FileCreator;

    public array $errorMessages = [];

    public function __construct(
        DirectiveInteractionService $interaction,
    ) {
        parent::__construct($interaction);
        $this->initFileCreator();
    }

    public function getSignature(): string
    {
        return 'test:file-creator';
    }

    public function getDescription(): string
    {
        return 'Test file creator trait';
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    public function shouldBootLaravel(): bool
    {
        return false;
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }

    // Surcharger la méthode error pour capturer les messages
    public function error(string $message): void
    {
        $this->errorMessages[] = $message;
    }

    // Expose protected methods for testing
    public function exposeToPascalCase(string $string): string
    {
        return $this->toPascalCase($string);
    }

    public function exposeToKebabCase(string $string): string
    {
        return $this->toKebabCase($string);
    }

    public function exposeExtractPathSegments(string $name): array
    {
        return $this->extractPathSegments($name);
    }

    public function exposeBuildNamespace(string $baseNamespace, string $subPath): string
    {
        return $this->buildNamespace($baseNamespace, $subPath);
    }

    public function exposeGetAppPath(string $baseDir, string $className, string $subPath = ''): string
    {
        return $this->getAppPath($baseDir, $className, $subPath);
    }

    public function exposeCreateFile(string $stubPath, string $destinationPath, array $replacements, bool $force = false): bool
    {
        // La méthode error() de cette classe sera appelée automatiquement par le trait
        return $this->createFile($stubPath, $destinationPath, $replacements, $force);
    }

    public function getLastError(): ?string
    {
        return ! empty($this->errorMessages) ? end($this->errorMessages) : null;
    }
}
