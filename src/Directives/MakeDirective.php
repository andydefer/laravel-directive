<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Tasks\CreateDirectiveFileTask;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * Directive to generate new directive classes.
 */
final class MakeDirective extends AbstractDirective
{
    public function __construct(
        DirectiveInteractionService $interaction,
        private readonly SignatureValidationService $signatureValidator,
        private readonly DirectiveNamingService $namingService,
        private readonly CreateDirectiveFileTask $fileTask,
    ) {
        parent::__construct($interaction);
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
        $aliases = new StringTypedCollection();
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

        if (!$validation->isValid) {
            $this->error($validation->error ?? 'Invalid directive name format');
            $this->line('');
            $this->line('Valid examples:');
            $this->line('  • user-create');
            $this->line('  • clean-log');
            $this->line('  • db-migrate-fresh');
            return ExitCode::INVALID_ARGUMENT;
        }

        $className = $this->namingService->generateClassName($name);
        $result = $this->fileTask->execute($className, $name);

        if (!$result->success) {
            $this->error($result->error ?? 'Failed to create directive');
            return ExitCode::FAILURE;
        }

        if (!is_dir(getcwd() . '/app/Directives/')) {
            $this->line('📁 Created directory: app/Directives/');
        }

        $this->info('✅ Directive created successfully!');
        $this->line("   Class: {$className}");
        $this->line("   Path: {$result->path}");
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
