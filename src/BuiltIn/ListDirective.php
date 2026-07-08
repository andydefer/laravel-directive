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

final class ListDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'list';
    }

    public function getDescription(): string
    {
        return 'List all available directives';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['ls', '-l', '--list']);
    }

    protected function execute(): ExitCode
    {
        $discovery = $this->getLaravel()->make(DirectiveDiscoveryService::class);
        $directives = $discovery->discover()->unique();
        $console = $this->getConsole();

        $console->title('Available Directives');
        $console->line();

        if ($directives->isEmpty()) {
            $console->alertWarning('No directives found.');

            return ExitCode::SUCCESS;
        }

        $console->line(sprintf('Total: %d directives', $directives->count()));
        $console->line();

        $categories = $this->groupByCategory($directives);

        foreach ($categories as $category => $items) {
            $console->info($category);

            $data = [];
            foreach ($items as $directive) {
                $description = $directive->description;
                if ($directive->aliases->isNotEmpty()) {
                    $description .= ' (aliases: '.$directive->aliases->join(', ').')';
                }
                $data[$directive->signature] = $description;
            }

            $console->raw(KeyValue::render(MapCollection::from($data), 2));
            $console->line();
        }

        return ExitCode::SUCCESS;
    }

    private function groupByCategory(DirectiveMetadataCollection $directives): array
    {
        $categories = [];

        foreach ($directives as $directive) {
            $category = $this->extractCategory($directive->signature);
            $categories[$category][] = $directive;
        }

        ksort($categories);

        return $categories;
    }

    private function extractCategory(string $signature): string
    {
        $parts = explode(':', $signature);

        if (count($parts) > 1) {
            return ucfirst($parts[0]);
        }

        return 'General';
    }
}
