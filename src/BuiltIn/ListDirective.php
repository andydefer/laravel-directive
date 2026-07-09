<?php

declare(strict_types=1);

namespace AndyDefer\Directive\BuiltIn;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;

/**
 * Built-in directive that lists all available directives.
 *
 * Discovers and displays all registered directives grouped by category.
 * Supports aliases for quick access (ls, -l, --list).
 */
final class ListDirective extends AbstractDirective
{
    /**
     * The default category for directives without a prefix.
     */
    private const DEFAULT_CATEGORY = 'General';

    /**
     * The indentation level for the key-value output.
     */
    private const INDENTATION_LEVEL = 2;

    /**
     * The signature used to invoke this directive.
     */
    public function getSignature(): string
    {
        return 'list';
    }

    /**
     * The human-readable description of this directive.
     */
    public function getDescription(): string
    {
        return 'List all available directives';
    }

    /**
     * The list of aliases that can be used to invoke this directive.
     */
    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['ls', '-l', '--list']);
    }

    /**
     * Executes the list directive.
     *
     * Discovers all directives, groups them by category, and displays
     * them in a formatted output.
     */
    protected function execute(): ExitCode
    {
        $directives = $this->discoverDirectives();
        $console = $this->getConsole();

        $console->title('Available Directives');
        $console->line();

        if ($directives->isEmpty()) {
            $console->alertWarning('No directives found.');

            return ExitCode::SUCCESS;
        }

        $this->displayDirectives($directives);

        return ExitCode::SUCCESS;
    }

    /**
     * Discovers and deduplicates all available directives.
     */
    private function discoverDirectives(): DirectiveMetadataCollection
    {
        $discovery = $this->getContainer()->make(DirectiveDiscoveryService::class);

        return $discovery->discover()->unique();
    }

    /**
     * Displays the directives grouped by category.
     */
    private function displayDirectives(DirectiveMetadataCollection $directives): void
    {
        $console = $this->getConsole();

        $console->line(sprintf('Total: %d directives', $directives->count()));
        $console->line();

        $categories = $this->groupByCategory($directives);

        foreach ($categories as $category => $items) {
            $console->info($category);
            $this->displayCategoryItems($items);
            $console->line();
        }
    }

    /**
     * Displays the items within a category.
     *
     * @param  DirectiveMetadataCollection  $items  The directives in the category
     */
    private function displayCategoryItems(DirectiveMetadataCollection $items): void
    {
        $console = $this->getConsole();
        $data = $this->buildCategoryData($items);

        $console->raw(KeyValue::render(MapCollection::from($data), self::INDENTATION_LEVEL));
    }

    /**
     * Builds the key-value data for a category.
     *
     * @param  DirectiveMetadataCollection  $items  The directives in the category
     * @return array<string, string> The formatted signature to description map
     */
    private function buildCategoryData(DirectiveMetadataCollection $items): array
    {
        $data = [];

        foreach ($items as $directive) {
            $description = $directive->description;

            if ($directive->aliases->isNotEmpty()) {
                $description .= ' (aliases: '.$directive->aliases->join(', ').')';
            }

            $data[$directive->signature] = $description;
        }

        return $data;
    }

    /**
     * Groups directives by their category.
     *
     * Categories are extracted from the signature prefix (e.g., "db:backup" -> "Db").
     *
     * @param  DirectiveMetadataCollection  $directives  The directives to group
     * @return array<string, DirectiveMetadataCollection> The grouped directives
     */
    private function groupByCategory(DirectiveMetadataCollection $directives): array
    {
        $categories = [];

        foreach ($directives as $directive) {
            $category = $this->extractCategory($directive->signature);

            if (! isset($categories[$category])) {
                $categories[$category] = new DirectiveMetadataCollection;
            }

            $categories[$category]->add($directive);
        }

        ksort($categories);

        return $categories;
    }

    /**
     * Extracts the category from a directive signature.
     *
     * @param  string  $signature  The directive signature (e.g., "db:backup")
     * @return string The extracted category (e.g., "Db")
     */
    private function extractCategory(string $signature): string
    {
        $parts = explode(':', $signature);

        if (count($parts) > 1) {
            return ucfirst($parts[0]);
        }

        return self::DEFAULT_CATEGORY;
    }
}
