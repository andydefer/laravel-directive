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
     * The package name.
     */
    private const PACKAGE_NAME = 'andydefer/laravel-directive';

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
     * Cache file path.
     */
    private const CACHE_FILE = '.directive-version-info.json';

    /**
     * Cache TTL in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

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
        $packagistData = $this->getPackagistData();

        return [
            '📦 Package' => self::PACKAGE_NAME,
            '🏷️ Version' => $packagistData['latest_version'] ?? 'Unknown',
            '🖥️ Laravel' => Application::VERSION,
            '🐘 PHP' => PHP_VERSION,
            '📅 Released' => $packagistData['release_date'] ?? 'Unknown',
            '📥 Downloads' => $packagistData['downloads'] ?? 'N/A',
            '👤 Author' => self::AUTHOR_NAME,
            '📧 Email' => self::AUTHOR_EMAIL,
            '📄 License' => self::LICENSE,
            '🔗 GitHub' => Link::renderWithText(self::REPOSITORY_URL, 'View on GitHub'),
        ];
    }

    /**
     * Get package data from Packagist API with file caching.
     *
     * @return array<string, mixed> Package data
     */
    private function getPackagistData(): array
    {
        $cacheFile = getcwd().'/'.self::CACHE_FILE;

        // ✅ Vérifier si le cache existe et est valide
        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached) && ! empty($cached)) {
                // ✅ Vérifier si le cache est encore valide
                $cacheTime = $cached['cached_at'] ?? 0;
                $cacheAge = time() - $cacheTime;

                if ($cacheAge < self::CACHE_TTL) {
                    return $cached['data'] ?? [];
                }
            }
        }

        // ✅ Appel API si cache invalide ou inexistant
        $data = $this->fetchFromPackagist();

        // ✅ Stocker en cache avec timestamp
        if (! empty($data)) {
            $cacheData = [
                'cached_at' => time(),
                'data' => $data,
            ];
            file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
        }

        return $data;
    }

    /**
     * Fetch package data from Packagist API.
     *
     * @return array<string, mixed> Package data
     */
    private function fetchFromPackagist(): array
    {
        $url = 'https://packagist.org/packages/'.self::PACKAGE_NAME.'.json';

        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: PHP/'.PHP_VERSION."\r\n",
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return [];
        }

        $data = json_decode($response, true);

        if (! isset($data['package']['versions'])) {
            return [];
        }

        $versions = array_keys($data['package']['versions']);
        $stableVersions = array_filter($versions, function ($v) {
            return preg_match('/^v?\d+\.\d+\.\d+$/', $v);
        });

        usort($stableVersions, function ($a, $b) {
            return version_compare($a, $b);
        });

        $latestVersion = end($stableVersions);

        $versionData = $data['package']['versions'][$latestVersion] ?? [];

        $downloads = $data['package']['downloads']['total'] ?? null;

        // ✅ Formater les téléchargements
        $formattedDownloads = $this->formatDownloads($downloads);

        return [
            'latest_version' => ltrim($latestVersion, 'v'),
            'release_date' => isset($versionData['time']) ? date('Y-m-d', strtotime($versionData['time'])) : null,
            'downloads' => $formattedDownloads,
            'description' => $data['package']['description'] ?? null,
        ];
    }

    /**
     * Format downloads count with K, M, B suffixes.
     *
     * @param  int|null  $downloads  Downloads count
     * @return string Formatted downloads
     */
    private function formatDownloads(?int $downloads): string
    {
        if ($downloads === null || $downloads === 0) {
            return 'N/A';
        }

        if ($downloads >= 1_000_000_000) {
            return number_format($downloads / 1_000_000_000, 2).'B';
        }

        if ($downloads >= 1_000_000) {
            return number_format($downloads / 1_000_000, 2).'M';
        }

        if ($downloads >= 1_000) {
            return number_format($downloads / 1_000, 1).'K';
        }

        return (string) $downloads;
    }

    /**
     * Displays the package description.
     */
    private function displayDescription(ConsoleInterface $console): void
    {
        $description = $this->getDescriptionFromCache();

        if ($description) {
            $console->info($description);
        } else {
            $console->info('A flexible CLI command system for Laravel that breaks free from Artisan\'s constraints.');
            $console->info('Directives introduces a clean separation between what your command does (business logic)');
            $console->info('and how it\'s presented (output/UI).');
        }

        $console->line();
    }

    /**
     * Get package description from cache.
     */
    private function getDescriptionFromCache(): ?string
    {
        $data = $this->getPackagistData();

        return $data['description'] ?? null;
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
