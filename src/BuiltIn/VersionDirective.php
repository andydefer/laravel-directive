<?php

declare(strict_types=1);

namespace AndyDefer\Directive\BuiltIn;

use AndyDefer\ConsoleWriter\Console\Components\Link;
use AndyDefer\ConsoleWriter\Console\Contracts\ConsoleInterface;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Illuminate\Foundation\Application;

/**
 * Built-in directive that displays version information.
 *
 * Shows detailed version information about the package, Laravel,
 * PHP, and the author.
 */
final class VersionDirective extends AbstractDirective
{
    /**
     * The current version of the package.
     */
    private const VERSION = '3.32.0';

    /**
     * The package name.
     */
    private const PACKAGE_NAME = 'laravel-directive';

    /**
     * The package author name.
     */
    private const AUTHOR_NAME = 'Andy Defer';

    /**
     * The package author email.
     */
    private const AUTHOR_EMAIL = 'andykanidimbu@gmail.com';

    /**
     * The package license.
     */
    private const LICENSE = 'MIT';

    /**
     * The repository URL.
     */
    private const REPOSITORY_URL = 'https://github.com/andydefer/laravel-directive';

    /**
     * The title displayed in the version output.
     */
    private const DISPLAY_TITLE = 'Laravel Directive';

    /**
     * The signature used to invoke this directive.
     */
    public function getSignature(): string
    {
        return 'version';
    }

    /**
     * The human-readable description of this directive.
     */
    public function getDescription(): string
    {
        return 'Display the application version';
    }

    /**
     * The list of aliases that can be used to invoke this directive.
     */
    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['-v', '--version']);
    }

    /**
     * Executes the version directive.
     *
     * Displays comprehensive version information including package details,
     * Laravel version, PHP version, and author information.
     */
    protected function execute(): ExitCode
    {
        $console = $this->getConsole();

        $this->displayHeader($console);
        $this->displayVersionInfo($console);
        $this->displayDescription($console);
        $this->displayRepositoryLink($console);

        return ExitCode::SUCCESS;
    }

    /**
     * Displays the main title header.
     */
    private function displayHeader(ConsoleInterface $console): void
    {
        $console->title(self::DISPLAY_TITLE);
        $console->line();
    }

    /**
     * Displays the version information.
     */
    private function displayVersionInfo(ConsoleInterface $console): void
    {
        $info = $this->buildVersionInfo();

        $console->keyValueWithValueColor($info, 'green');
        $console->line();
    }

    /**
     * Builds the version information array.
     *
     * @return array<string, string> The version information
     */
    private function buildVersionInfo(): array
    {
        return [
            'Package' => self::PACKAGE_NAME,
            'Version' => self::VERSION,
            'Laravel' => Application::VERSION,
            'PHP' => PHP_VERSION,
            'Author' => self::AUTHOR_NAME,
            'Email' => self::AUTHOR_EMAIL,
            'License' => self::LICENSE,
            'GitHub' => Link::renderWithText(self::REPOSITORY_URL, 'View on GitHub'),
        ];
    }

    /**
     * Displays the package description.
     */
    private function displayDescription(ConsoleInterface $console): void
    {
        $console->info('A flexible CLI command system for Laravel that breaks free from Artisan\'s constraints.');
        $console->info('Directives introduces a clean separation between what your command does (business logic)');
        $console->info('and how it\'s presented (output/UI).');
        $console->line();
    }

    /**
     * Displays the repository link.
     */
    private function displayRepositoryLink(ConsoleInterface $console): void
    {
        $console->line('📦 '.Link::renderWithText(
            self::REPOSITORY_URL,
            self::REPOSITORY_URL
        ));
    }
}
