<?php

declare(strict_types=1);

namespace AndyDefer\Directive\BuiltIn;

use AndyDefer\ConsoleWriter\Console\Components\JsonViewer;
use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\SignatureParser\CommentManager;
use AndyDefer\SignatureParser\SignatureDocumentor;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

final class ListDirective extends AbstractDirective
{
    private const DEFAULT_CATEGORY = 'General';

    private const INDENTATION_LEVEL = 5;

    private const SUGGESTION_LIMIT = 5;

    private const LEVENSHTEIN_THRESHOLD = 2;

    public function getSignature(): string
    {
        return 'list {source=?}#"Directive name to inspect" ::format->[json,default]=default#"Output format (json or default)"';
    }

    public function getDescription(): string
    {
        return 'List all available directives or show details for a specific directive';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['ls', '-l', '--list']);
    }

    protected function execute(): ExitCode
    {
        $directives = $this->discoverDirectives();
        $console = $this->getConsole();

        $source = $this->getArgument('source');
        $format = $this->getArgument('format');

        if ($source !== null) {
            return $this->showDirectiveDetails($directives, $source, $format);
        }

        return $this->listAllDirectives($directives);
    }

    private function listAllDirectives(DirectiveMetadataCollection $directives): ExitCode
    {
        $console = $this->getConsole();

        $console->title('Available Directives');
        $console->line();

        if ($directives->isEmpty()) {
            $console->alertWarning('No directives found.');

            return ExitCode::SUCCESS;
        }

        $console->line(sprintf('Total: %d directives', $directives->count()));
        $console->line();

        $categories = $this->groupDirectivesByCategory($directives);

        foreach ($categories as $category => $items) {
            $console->info($this->cleanCategoryName($category));
            $this->renderCategoryItems($items);
            $console->line();
        }

        return ExitCode::SUCCESS;
    }

    private function showDirectiveDetails(
        DirectiveMetadataCollection $directives,
        string $source,
        ?string $format = null
    ): ExitCode {
        $directive = $this->findDirective($directives, $source);

        if ($directive === null) {
            return $this->handleDirectiveNotFound($directives, $source);
        }

        return $format === 'json'
            ? $this->renderDirectiveAsJson($directive)
            : $this->renderDirectiveAsDefault($directive);
    }

    private function findDirective(DirectiveMetadataCollection $directives, string $source): ?DirectiveMetadataRecord
    {
        foreach ($directives as $directive) {
            $commandName = $this->extractCommandName($directive->signature);

            if ($commandName === $source) {
                return $directive;
            }

            foreach ($directive->aliases as $alias) {
                if ($alias === $source) {
                    return $directive;
                }
            }
        }

        return null;
    }

    private function extractCommandName(string $signature): string
    {
        return (new SignatureStructureVO($signature))->getSource();
    }

    private function handleDirectiveNotFound(DirectiveMetadataCollection $directives, string $source): ExitCode
    {
        $console = $this->getConsole();
        $console->error("Directive not found: {$source}");

        $suggestions = $this->findSuggestions($directives, $source);

        if (! empty($suggestions)) {
            $console->line();
            $console->info('💡 Did you mean:');
            foreach ($suggestions as $suggestion) {
                $console->line("  • {$suggestion}");
            }
        }

        return ExitCode::NOT_FOUND;
    }

    private function renderDirectiveAsJson(DirectiveMetadataRecord $directive): ExitCode
    {
        $console = $this->getConsole();

        $documentation = SignatureDocumentor::generate($directive->signature, 'array');
        $cleanSignature = $this->cleanSignature($directive->signature);

        $payload = [
            'directive' => [
                'signature' => $cleanSignature,
                'description' => $directive->description,
                'class' => $directive->class,
                'aliases' => $directive->aliases->toArray(),
            ],
            'documentation' => $documentation,
        ];

        $console->title('📄 Directive Details (JSON)');
        $console->line();
        echo JsonViewer::render($payload)."\n";

        return ExitCode::SUCCESS;
    }

    private function cleanSignature(string $signature): string
    {
        $commentManager = new CommentManager;
        $cleaned = $commentManager->extractComments($signature);
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        return $cleaned;
    }

    private function renderDirectiveAsDefault(DirectiveMetadataRecord $directive): ExitCode
    {
        $console = $this->getConsole();

        $documentation = SignatureDocumentor::generate($directive->signature, 'array');
        $cleanSignature = $this->cleanSignature($directive->signature);

        $console->title(sprintf('📋 Details for: %s', $documentation['source']));
        $console->line();

        $this->renderGeneralInfo($directive, $cleanSignature);
        $this->renderArguments($documentation, 'requireds', 'Required Arguments:', 'yellow');
        $this->renderArguments($documentation, 'defaults', 'Default Arguments:', 'green');
        $this->renderEnums($documentation);
        $this->renderArguments($documentation, 'variadics', 'Variadic Arguments:', 'magenta');
        $this->renderFlags($documentation);
        $this->renderExample($documentation);

        return ExitCode::SUCCESS;
    }

    private function renderGeneralInfo(DirectiveMetadataRecord $directive, string $cleanSignature): void
    {
        $console = $this->getConsole();

        $info = MapCollection::from([
            'Signature' => $cleanSignature,
            'Description' => $directive->description,
            'Class' => $directive->class,
        ]);

        if ($directive->aliases->isNotEmpty()) {
            $info = $info->put('Aliases', $directive->aliases->join(', '));
        }

        $console->raw(KeyValue::renderWithValueColor($info, 'cyan'));
        $console->line();
    }

    private function renderArguments(array $documentation, string $key, string $title, string $color): void
    {
        $console = $this->getConsole();

        if (empty($documentation[$key])) {
            return;
        }

        $console->info($title);
        $data = MapCollection::from([]);

        foreach ($documentation[$key] as $item) {
            $label = $item['name'];

            if (isset($item['comment'])) {
                $label .= " -> [{$item['comment']}]";
            }

            if ($key === 'variadics' && isset($item['restrictions']) && ! empty($item['restrictions'])) {
                $value = 'Allowed values: '.implode(', ', $item['restrictions']);
            } elseif ($key === 'defaults') {
                $value = $this->normalizeDefaultValue($item['default']);
            } elseif ($key === 'requireds') {
                $value = 'Required';
            } else {
                $value = 'Multiple values allowed';
            }

            $data = $data->put($label, $value);
        }

        $console->raw(KeyValue::renderWithValueColor($data, $color));
        $console->line();
    }

    private function renderEnums(array $documentation): void
    {
        $console = $this->getConsole();

        if (empty($documentation['enums'])) {
            return;
        }

        $console->info('Enums:');
        $data = MapCollection::from([]);

        foreach ($documentation['enums'] as $enum) {
            $allowed = implode('|', $enum['allowed_values']);
            $state = $this->getEnumState($enum);
            $label = $enum['name'];

            if (isset($enum['comment'])) {
                $label .= " -> [{$enum['comment']}]";
            }

            $data = $data->put($label, "{$allowed} ({$state})");
        }

        $console->raw(KeyValue::renderWithValueColor($data, 'blue'));
        $console->line();
    }

    private function renderFlags(array $documentation): void
    {
        $console = $this->getConsole();

        if (empty($documentation['flags'])) {
            return;
        }

        $console->info('Flags:');
        $data = MapCollection::from([]);

        foreach ($documentation['flags'] as $flag) {
            $label = "--{$flag['name']}";

            if (isset($flag['comment'])) {
                $label .= " -> [{$flag['comment']}]";
            }

            $data = $data->put($label, 'Boolean flag');
        }

        $console->raw(KeyValue::renderWithValueColor($data, 'cyan'));
        $console->line();
    }

    private function renderExample(array $documentation): void
    {
        $console = $this->getConsole();
        $console->info('Example:');

        $example = $this->buildExampleFromDocumentation($documentation);
        $console->raw(KeyValue::render(
            MapCollection::from(['Usage' => $example]),
            self::INDENTATION_LEVEL
        ));
        $console->line();
    }

    private function normalizeDefaultValue(mixed $value): string
    {
        if ($value === null || $value === '?' || $value === '~' || $value === '') {
            return 'NULL';
        }

        return (string) $value;
    }

    private function getEnumState(array $enum): string
    {
        if ($enum['is_required']) {
            return 'required';
        }

        if ($enum['is_optional']) {
            return 'optional';
        }

        return sprintf('default: %s', $enum['default_value'] ?? 'NULL');
    }

    private function groupDirectivesByCategory(DirectiveMetadataCollection $directives): array
    {
        $categories = [];

        foreach ($directives as $directive) {
            $category = $this->extractCategory($directive->signature);
            $cleanCategory = $this->cleanCategoryName($category);

            if (! isset($categories[$cleanCategory])) {
                $categories[$cleanCategory] = new DirectiveMetadataCollection;
            }

            $categories[$cleanCategory]->add($directive);
        }

        ksort($categories);

        return $categories;
    }

    private function extractCategory(string $signature): string
    {
        $commentManager = new CommentManager;
        $cleanSignature = $commentManager->extractComments($signature);

        $parts = explode(':', $cleanSignature);

        if (count($parts) > 1) {
            $category = trim($parts[0]);

            if (! empty($category) && ! str_contains($category, '{') && ! str_contains($category, '}')) {
                return ucfirst($category);
            }
        }

        return self::DEFAULT_CATEGORY;
    }

    private function cleanCategoryName(string $category): string
    {
        $commentManager = new CommentManager;
        $cleaned = $commentManager->extractComments($category);

        if (empty(trim($cleaned))) {
            $cleaned = explode('{', $category)[0];
            $cleaned = trim($cleaned);
        }

        if (empty($cleaned)) {
            return self::DEFAULT_CATEGORY;
        }

        return $cleaned;
    }

    private function renderCategoryItems(DirectiveMetadataCollection $items): void
    {
        $console = $this->getConsole();
        $data = $this->buildCategoryData($items);
        $console->raw(KeyValue::render(MapCollection::from($data), self::INDENTATION_LEVEL));
    }

    private function buildCategoryData(DirectiveMetadataCollection $items): array
    {
        $data = [];

        foreach ($items as $directive) {
            $description = $directive->description;

            if ($directive->aliases->isNotEmpty()) {
                $description .= sprintf(' (aliases: %s)', $directive->aliases->join(', '));
            }

            $commandName = $this->extractCommandName($directive->signature);
            $cleanName = $this->cleanCommandName($commandName);

            $data[$cleanName] = $description;
        }

        return $data;
    }

    private function cleanCommandName(string $name): string
    {
        $commentManager = new CommentManager;
        $cleaned = $commentManager->extractComments($name);

        if (empty(trim($cleaned))) {
            $cleaned = explode('{', $name)[0];
            $cleaned = trim($cleaned);
        }

        return empty($cleaned) ? $name : $cleaned;
    }

    private function findSuggestions(DirectiveMetadataCollection $directives, string $source): array
    {
        $suggestions = [];

        foreach ($directives as $directive) {
            $commandName = $this->extractCommandName($directive->signature);

            if ($this->isSimilar($source, $commandName)) {
                $suggestions[] = $commandName;
            }

            foreach ($directive->aliases as $alias) {
                if ($this->isSimilar($source, $alias)) {
                    $suggestions[] = $alias;
                }
            }
        }

        sort($suggestions);

        return array_slice($suggestions, 0, self::SUGGESTION_LIMIT);
    }

    private function isSimilar(string $needle, string $haystack): bool
    {
        return levenshtein($needle, $haystack) <= self::LEVENSHTEIN_THRESHOLD;
    }

    private function buildExampleFromDocumentation(array $documentation): string
    {
        $parts = [$documentation['source']];

        foreach ($documentation['requireds'] as $arg) {
            $parts[] = sprintf('<%s>', $arg['name']);
        }

        foreach ($documentation['defaults'] as $arg) {
            $parts[] = sprintf('[%s=value]', $arg['name']);
        }

        foreach ($documentation['enums'] as $enum) {
            $values = implode('|', $enum['allowed_values']);
            $parts[] = $enum['is_required']
                ? sprintf('<%s:%s>', $enum['name'], $values)
                : sprintf('[%s:%s=%s]', $enum['name'], $values, $enum['default_value'] ?? 'NULL');
        }

        foreach ($documentation['variadics'] as $variadic) {
            $parts[] = sprintf('[%s...]', $variadic['name']);
        }

        foreach ($documentation['flags'] as $flag) {
            $parts[] = sprintf('[--%s]', $flag['name']);
        }

        return implode(' ', $parts);
    }

    private function discoverDirectives(): DirectiveMetadataCollection
    {
        return $this->getKernel()->discover()->unique();
    }
}
