<?php

declare(strict_types=1);

namespace AndyDefer\Directive\BuiltIn;

use AndyDefer\ConsoleWriter\Console\Components\Link;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Illuminate\Foundation\Application;

final class VersionDirective extends AbstractDirective
{
    private const VERSION = '3.32.0';

    public function getSignature(): string
    {
        return 'version';
    }

    public function getDescription(): string
    {
        return 'Display the application version';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['-v', '--version']);
    }

    protected function execute(): ExitCode
    {
        $console = $this->getConsole();
        $app = $this->getLaravel();

        $console->title('Laravel Directive');
        $console->line();

        $console->keyValueWithValueColor([
            'Package' => 'laravel-directive',
            'Version' => self::VERSION,
            'Laravel' => Application::VERSION,
            'PHP' => PHP_VERSION,
            'Author' => 'Andy Defer',
            'Email' => 'andykanidimbu@gmail.com',
            'License' => 'MIT',
            'GitHub' => Link::renderWithText('https://github.com/andydefer/laravel-directive', 'View on GitHub'),
        ], 'green');

        $console->line();
        $console->info('A flexible CLI command system for Laravel that breaks free from Artisan\'s constraints.');
        $console->info('Directives introduces a clean separation between what your command does (business logic)');
        $console->info('and how it\'s presented (output/UI).');

        $console->line();
        $console->line('📦 '.Link::renderWithText(
            'https://github.com/andydefer/laravel-directive',
            'https://github.com/andydefer/laravel-directive'
        ));

        return ExitCode::SUCCESS;
    }
}
