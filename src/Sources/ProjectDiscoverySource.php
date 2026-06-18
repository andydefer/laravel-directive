<?php

// src/Sources/ProjectDiscoverySource.php

declare(strict_types=1);

namespace AndyDefer\Directive\Sources;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\DiscoverySourceInterface;
use AndyDefer\Directive\Services\DirectiveMetadataExtractorService;

final class ProjectDiscoverySource implements DiscoverySourceInterface
{
    public function __construct(
        private readonly DirectiveConfigInterface $config,
        private readonly DirectiveMetadataExtractorService $extractor,
    ) {}

    public function discover(): DirectiveMetadataCollection
    {
        $results = new DirectiveMetadataCollection;
        $path = $this->config->directivesPath();

        if ($path !== '' && is_dir($path)) {
            $files = glob($path.'/*.php');
            if ($files !== false) {
                foreach ($files as $file) {
                    $metadata = $this->extractor->extractFromFile($file);
                    if ($metadata !== null) {
                        $results->add($metadata);
                    }
                }
            }
        }

        return $results;
    }

    public function getName(): string
    {
        return 'project';
    }
}
