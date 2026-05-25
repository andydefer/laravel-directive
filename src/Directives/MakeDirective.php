<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Traits\FileCreator;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * Directive to generate new directive classes.
 */
final class MakeDirective extends AbstractDirective
{
    use FileCreator;

    private const DIRECTIVES_PATH = '/app/Directives/';

    private string $stubPath;

    public function __construct(
        DirectiveInteractionService $interaction,
        private readonly SignatureValidationService $signatureValidator,
        private readonly DirectiveNamingService $namingService,
        ?string $stubPath = null,
    ) {
        parent::__construct($interaction);
        $this->initFileCreator();
        $this->stubPath = $stubPath ?? __DIR__.'/../../stubs/directive.stub';
    }

    public function getSignature(): string
    {
        return 'make-directive {name}';
    }

    public function getDescription(): string
    {
        return 'Create a new directive class';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('create-directive');
        $aliases->add('make-cmd');

        return $aliases;
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->showUsageError();

            return ExitCode::INVALID_ARGUMENT;
        }

        $validation = $this->signatureValidator->validate($name);

        if (! $validation->isValid) {
            $this->error($validation->error ?? 'Invalid directive name format');
            $this->line('');
            $this->line('Valid examples:');
            $this->line('  • user-create');
            $this->line('  • clean-log');
            $this->line('  • db-migrate-fresh');

            return ExitCode::INVALID_ARGUMENT;
        }

        $className = $this->namingService->generateClassName($name);
        $destinationPath = $this->getAppPath(self::DIRECTIVES_PATH, $className);

        if (! $this->createFile($this->stubPath, $destinationPath, [
            '{{signature}}' => $name,
            '{{class}}' => $className,
            '{{description}}' => "Description for {$className}",
            '{{date}}' => date('Y-m-d H:i:s'),
        ])) {
            return ExitCode::FAILURE;
        }

        $this->info('✅ Directive created successfully!');
        $this->line("   Class: {$className}");
        $this->line("   Path: {$destinationPath}");
        $this->line("   Signature: {$name}");

        return ExitCode::SUCCESS;
    }

    private function showUsageError(): void
    {
        $this->error('Directive name is required');
        $this->line('Usage: directive make-directive <name>');
        $this->line('Example: directive make-directive user-create');
        $this->line('');
        $this->line('Use only letters, numbers, and hyphens. Must start with a letter.');
    }
}
