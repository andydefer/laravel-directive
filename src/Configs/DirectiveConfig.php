<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class DirectiveConfig implements DirectiveConfigInterface
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function basePath(): string
    {
        $path = $this->config->get('directive.base_path');

        if (is_string($path) && $path !== '') {
            return $path;
        }

        return getcwd();
    }

    public function getDirectories(): array
    {
        $directories = $this->config->get('directive.directories', [
            'src/Directives',
            'app/Directives',
        ]);

        return is_array($directories) ? $directories : [];
    }

    public function getReservedSignatures(): array
    {
        $reserved = $this->config->get('directive.reserved', [
            '-h',
            '--help',
            '-v',
            '--version',
            '-l',
            '--list',
            'help',
            'list',
            'version',
        ]);

        return is_array($reserved) ? $reserved : [];
    }

    public function setReservedSignatures(array $signatures): void
    {
        $this->config->set('directive.reserved', $signatures);
    }

    public function getVendorDir(): string
    {
        return $this->basePath().'/vendor';
    }

    public function getComposerPath(): string
    {
        return $this->basePath().'/composer.json';
    }

    public function isDebug(): bool
    {
        return $this->config->get('directive.debug', false);
    }

    public function getMaxDepth(): int
    {
        $depth = $this->config->get('directive.max_depth', 3);

        return (int) $depth;
    }

    public function getCustomSources(): array
    {
        $sources = $this->config->get('directive.custom_sources', []);

        return is_array($sources) ? $sources : [];
    }
}
