<?php

declare(strict_types=1);

namespace AndyDefer\Directive\BuiltIn;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\ConsoleWriter\Console\Components\TableList;
use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\DiscoverySource;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Built-in directive that audits the discovery system and displays problems.
 *
 * This directive helps debug issues with directive discovery by showing:
 * - All problems encountered during discovery
 * - The current discovery configuration
 * - Statistics about discovered directives
 *
 * @example
 * ./bin/directive kernel:audit
 * ./bin/directive kernel:audit --verbose
 * ./bin/directive kernel:audit --format=list
 */
final class KernelAuditDirective extends AbstractDirective
{
    private const METRICS_THRESHOLD_WARNING = 5;

    public function getSignature(): string
    {
        return 'kernel:audit {format=table} {--verbose}';
    }

    public function getDescription(): string
    {
        StrictAssociative::class;

        return 'Audit the kernel discovery system and display problems and metrics';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['audit']);
    }

    protected function execute(): ExitCode
    {
        $verbose = $this->isFlagActive('verbose');
        $format = $this->getArgument('format') ?? 'table';

        $kernel = $this->getKernel();
        $console = $this->getConsole();

        $this->displayHeader($console);

        // 2. Display discovery problems
        $problems = $kernel->getProblems();
        $this->displayProblems($console, $problems, $verbose, $format);

        // 3. Display statistics
        $this->displayStatistics($console, $kernel, $verbose);

        // 4. Display configuration
        if ($verbose) {
            $this->displayConfiguration($console, $kernel);
        }

        $this->displayFooter($console);

        return $problems->isEmpty() ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }

    private function displayHeader(Console $console): void
    {
        $console->separator('=', 60);
        $console->title('🔍 Kernel Audit Report');
        $console->separator('=', 60);
        $console->newLine();
    }

    private function displayProblems(Console $console, ListCollection $problems, bool $verbose, string $format): void
    {
        if ($problems->isEmpty()) {
            $console->info('✅ No problems found in discovery system');
            $console->newLine();

            return;
        }

        $console->error('❌ '.$problems->count().' problem(s) found');
        $console->newLine();

        if ($format === 'table') {
            $this->displayProblemsTable($console, $problems, $verbose);
        } else {
            $this->displayProblemsList($console, $problems, $verbose);
        }
    }

    private function displayProblemsTable(Console $console, ListCollection $problems, bool $verbose): void
    {
        $headers = ListCollection::from(['Key', 'Context', 'Message', 'Timestamp']);

        if ($verbose) {
            $headers = $headers->add('Context Data');
        }

        $rows = new ListCollection;

        foreach ($problems as $problem) {
            $row = ListCollection::from([
                $problem->get('key'),
                $problem->get('context'),
                $problem->get('message'),
                $problem->get('timestamp'),
            ]);

            if ($verbose) {
                $contextData = $problem->get('context_data');
                $row = $row->add(! empty($contextData) ? json_encode($contextData->toArray()) : 'N/A');
            }

            // ✅ Réassigner $rows avec la nouvelle instance
            $rows = $rows->add($row);
        }

        echo TableList::renderWithTitle($headers, $rows, '📋 Discovery Problems');
        $console->newLine();
    }

    private function displayProblemsList(Console $console, ListCollection $problems, bool $verbose): void
    {
        foreach ($problems as $problem) {
            $console->error('  ❌ '.$problem->get('key'));
            $console->line('     Context: '.$problem->get('context'));
            $console->line('     Message: '.$problem->get('message'));
            $console->line('     Time: '.$problem->get('timestamp'));

            if ($verbose) {
                $contextData = $problem->get('context_data');
                if ($contextData && $contextData->isNotEmpty()) {
                    $console->line('     Data: '.json_encode($contextData->toArray()));
                }
            }
            $console->newLine();
        }
    }

    private function displayStatistics(Console $console, DirectiveKernel $kernel, bool $verbose): void
    {
        $directives = $kernel->discover();

        $console->info('📊 Discovery Statistics');
        $console->separator('-', 40);

        // Use KeyValue for statistics
        $data = MapCollection::from([
            'Total directives' => $directives->count(),
            'Unique classes' => $directives->uniqueByClass()->count(),
            'Problems found' => $kernel->getProblems()->count(),
            'Sources enabled' => $this->getEnabledSourcesCount($kernel),
            'Auto-discovery' => $kernel->isAutoDiscoveryEnabled() ? '✅ Enabled' : '❌ Disabled',
            'Silent mode' => $kernel->isVerbose() ? '✅ Enabled' : '❌ Disabled',
            'Max depth' => $kernel->getMaxDepth(),
        ]);

        echo KeyValue::renderWithValueColor($data, 'cyan');
        $console->newLine();

        if ($verbose) {
            $this->displayDirectivesBreakdown($console, $directives);
        }
    }

    private function displayDirectivesBreakdown(Console $console, DirectiveMetadataCollection $directives): void
    {
        $console->info('📋 Directives Breakdown');
        $console->separator('-', 40);

        $headers = ListCollection::from(['Signature', 'Class', 'Aliases']);
        $rows = new ListCollection;

        foreach ($directives as $directive) {
            $rows->add(ListCollection::from([
                $directive->signature,
                $directive->class,
                $directive->aliases->join(', ') ?: 'None',
            ]));
        }

        echo TableList::renderWithTitle($headers, $rows, '📋 Directives List');
        $console->newLine();
    }

    private function displayConfiguration(Console $console, DirectiveKernel $kernel): void
    {
        $container = $kernel->getApplication();

        $console->info('⚙️ Configuration');
        $console->separator('-', 40);

        $data = MapCollection::from([
            'Base path' => $container->basePath(),
            'Log path' => $kernel->getLogger()->getBasePath(),
            'Version' => $container->version() ?? 'N/A',
            'Max depth' => $kernel->getMaxDepth(),
            'Auto-discovery' => $kernel->isAutoDiscoveryEnabled() ? 'Enabled' : 'Disabled',
        ]);

        echo KeyValue::renderWithValueColor($data, 'yellow');
        $console->newLine();

        // Display ignored sources
        $this->displayIgnoredSources($console, $kernel);
    }

    private function displayIgnoredSources(Console $console, DirectiveDiscoveryService $kernel): void
    {
        $sources = [
            'BUILTIN',
            'WORKSPACE',
            'VENDOR',
            'CUSTOM',
        ];

        $ignored = [];
        foreach ($sources as $source) {
            if ($kernel->isSourceIgnored($source)) {
                $ignored[] = $source;
            }
        }

        if (! empty($ignored)) {
            $console->info('⚠️ Ignored Sources: '.implode(', ', $ignored));
            $console->newLine();
        }
    }

    private function getEnabledSourcesCount(DirectiveDiscoveryService $kernel): int
    {
        $sources = [
            DiscoverySource::BUILTIN,
            DiscoverySource::WORKSPACE,
            DiscoverySource::VENDOR,
            DiscoverySource::CUSTOM,
        ];

        $enabled = 0;
        foreach ($sources as $source) {
            if (! $kernel->isSourceIgnored($source)) {
                $enabled++;
            }
        }

        return $enabled;
    }

    private function displayFooter(Console $console): void
    {
        $console->newLine();
        $console->separator('=', 60);
        $console->success('✅ Audit completed');
        $console->separator('=', 60);
    }
}
