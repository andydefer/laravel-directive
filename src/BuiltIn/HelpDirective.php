<?php

declare(strict_types=1);

namespace AndyDefer\Directive\BuiltIn;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class HelpDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'help';
    }

    public function getDescription(): string
    {
        return 'Display help information';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['-h', '--help']);
    }

    protected function execute(): ExitCode
    {
        // Appeler la directive list pour afficher la liste des commandes
        $this->call('list');

        // Ajouter les infos supplémentaires spécifiques à help
        $this->newLine();
        $this->line('Global options:');
        $this->line('  --help, -h     Show this help message');
        $this->line('  --list, -l     List all available commands');
        $this->line('  --version, -v  Show the application version');

        return ExitCode::SUCCESS;
    }
}
