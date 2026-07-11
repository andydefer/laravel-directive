<?php

declare(strict_types=1);

namespace AndyDefer\Directive\BuiltIn;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

/**
 * Built-in directive that lists all available directives.
 *
 * Discovers and displays all registered directives grouped by category.
 * Supports aliases for quick access (ls, -l, --list).
 * Supports search by source (e.g., list test:call).
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
    private const INDENTATION_LEVEL = 5;

    /**
     * The signature used to invoke this directive.
     */
    public function getSignature(): string
    {
        return 'list {source=?}';
    }

    /**
     * The human-readable description of this directive.
     */
    public function getDescription(): string
    {
        return 'List all available directives or show details for a specific directive';
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
     * them in a formatted output. If a source is provided, shows details
     * for that specific directive.
     */
    protected function execute(): ExitCode
    {
        $directives = $this->discoverDirectives();
        $console = $this->getConsole();

        $source = $this->argument('source');

        // ✅ Si une source est fournie, afficher les détails de la directive
        if ($source !== null) {
            return $this->showDirectiveDetails($directives, $source);
        }

        // ✅ Sinon, afficher la liste complète
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
     * Shows detailed information about a specific directive.
     *
     * @param  DirectiveMetadataCollection  $directives  The collection of all directives
     * @param  string  $source  The command name to search for
     * @return ExitCode The exit code
     */
    private function showDirectiveDetails(DirectiveMetadataCollection $directives, string $source): ExitCode
    {
        $console = $this->getConsole();

        // ✅ Rechercher la directive par source
        $foundDirective = null;

        foreach ($directives as $directive) {
            // ✅ Utiliser SignatureStructureVO pour extraire la source
            $structure = new SignatureStructureVO($directive->signature);
            $commandName = $structure->getSource();

            if ($commandName === $source) {
                $foundDirective = $directive;
                break;
            }

            // ✅ Vérifier aussi les alias
            foreach ($directive->aliases as $alias) {
                if ($alias === $source) {
                    $foundDirective = $directive;
                    break 2;
                }
            }
        }

        if ($foundDirective === null) {
            $console->error("Directive not found: {$source}");

            // ✅ Suggestions
            $suggestions = $this->getSuggestions($directives, $source);
            if (! empty($suggestions)) {
                $console->line();
                $console->info('💡 Did you mean:');
                foreach ($suggestions as $suggestion) {
                    $console->line("  • {$suggestion}");
                }
            }

            return ExitCode::NOT_FOUND;
        }

        // ✅ Afficher les détails de la directive
        $this->displayDirectiveDetails($foundDirective);

        return ExitCode::SUCCESS;
    }

    /**
     * Displays detailed information about a specific directive.
     *
     * @param  DirectiveMetadataRecord  $directive  The directive to display
     */
    private function displayDirectiveDetails(DirectiveMetadataRecord $directive): void
    {
        $console = $this->getConsole();

        // ✅ Utiliser SignatureStructureVO pour la signature
        $structure = new SignatureStructureVO($directive->signature);
        $commandName = $structure->getSource();

        $console->title("📋 Details for: {$commandName}");
        $console->line();

        // ✅ Informations générales - valeurs en cyan
        $generalInfo = MapCollection::from([
            'Signature' => $directive->signature,
            'Description' => $directive->description,
            'Class' => $directive->class,
        ]);

        if ($directive->aliases->isNotEmpty()) {
            $generalInfo = $generalInfo->put('Aliases', $directive->aliases->join(', '));
        }

        $console->raw(KeyValue::renderWithValueColor($generalInfo, 'cyan'));
        $console->line();

        // ✅ Arguments requis - valeurs en jaune
        if ($structure->hasRequireds()) {
            $console->info('Required Arguments:');
            $requiredData = MapCollection::from(
                array_fill_keys($structure->getRequireds(), 'Required')
            );
            $console->raw(KeyValue::renderWithValueColor($requiredData, 'yellow'));
            $console->line();
        }

        // ✅ Arguments avec valeurs par défaut - valeurs en vert
        if ($structure->hasDefaults()) {
            $console->info('Default Arguments:');
            $defaults = $structure->getDefaults();

            // ✅ Remplacer les valeurs null ou vides par 'NULL'
            $defaultsWithNull = array_map(
                fn ($value) => ($value === null || $value === '') ? 'NULL' : $value,
                $defaults
            );

            $defaultData = MapCollection::from($defaultsWithNull);
            $console->raw(KeyValue::renderWithValueColor($defaultData, 'green'));
            $console->line();
        }

        // ✅ Arguments variadiques - valeurs en magenta
        if ($structure->hasVariadics()) {
            $console->info('Variadic Arguments:');
            $variadicData = MapCollection::from(
                array_fill_keys($structure->getVariadics(), 'Multiple values allowed')
            );
            $console->raw(KeyValue::renderWithValueColor($variadicData, 'magenta'));
            $console->line();
        }

        // ✅ Flags - valeurs en cyan
        if ($structure->hasFlags()) {
            $console->info('Flags:');
            $flagsData = MapCollection::from(
                array_fill_keys($structure->getFlags(), 'Boolean flag')
            );
            $console->raw(KeyValue::renderWithValueColor($flagsData, 'cyan'));
            $console->line();
        }

        // ✅ Exemple d'utilisation - valeurs en blanc (défaut)
        $console->info('Example:');
        $example = $this->buildExample($commandName, $structure);
        $console->raw(KeyValue::render(
            MapCollection::from(['Usage' => $example]),
            self::INDENTATION_LEVEL
        ));
        $console->line();
    }

    /**
     * Builds an example usage string for a directive.
     *
     * @param  string  $commandName  The command name
     * @param  SignatureStructureVO  $structure  The signature structure
     * @return string The example usage
     */
    private function buildExample(string $commandName, SignatureStructureVO $structure): string
    {
        $parts = [$commandName];

        // ✅ Ajouter les arguments requis
        foreach ($structure->getRequireds() as $required) {
            $parts[] = "<{$required}>";
        }

        // ✅ Ajouter les arguments par défaut (optionnels)
        foreach (array_keys($structure->getDefaults()) as $default) {
            $parts[] = "[{$default}=value]";
        }

        // ✅ Ajouter les variadics
        foreach ($structure->getVariadics() as $variadic) {
            $parts[] = "[{$variadic}...]";
        }

        // ✅ Ajouter les flags
        foreach ($structure->getFlags() as $flag) {
            $parts[] = "[--{$flag}]";
        }

        return implode(' ', $parts);
    }

    /**
     * Gets suggestions for a source that wasn't found.
     *
     * @param  DirectiveMetadataCollection  $directives  The collection of all directives
     * @param  string  $source  The source that wasn't found
     * @param  int  $limit  The maximum number of suggestions
     * @return array<string> The suggestions
     */
    private function getSuggestions(DirectiveMetadataCollection $directives, string $source, int $limit = 5): array
    {
        $suggestions = [];

        foreach ($directives as $directive) {
            $structure = new SignatureStructureVO($directive->signature);
            $commandName = $structure->getSource();

            $levenshtein = levenshtein($source, $commandName);

            // ✅ Seuil de 2 pour les suggestions
            if ($levenshtein <= 2) {
                $suggestions[] = $commandName;
            }

            // ✅ Vérifier aussi les alias
            foreach ($directive->aliases as $alias) {
                $levenshtein = levenshtein($source, $alias);
                if ($levenshtein <= 2 && ! in_array($alias, $suggestions, true)) {
                    $suggestions[] = $alias;
                }
            }
        }

        sort($suggestions);

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Discovers and deduplicates all available directives.
     */
    private function discoverDirectives(): DirectiveMetadataCollection
    {
        return $this->getKernel()->discover()->unique();
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

            // ✅ Utiliser SignatureStructureVO pour extraire la source
            $structure = new SignatureStructureVO($directive->signature);
            $commandName = $structure->getSource();

            $data[$commandName] = $description;
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
