<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Scanners;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;

/**
 * Scans PHP files to discover directive classes.
 *
 * This scanner uses AST parsing to reliably detect classes that extend
 * AbstractDirective, even with complex syntax or aliased imports.
 */
final class DirectiveClassScanner implements DirectiveScannerInterface
{
    public function __construct(
        private readonly FileSystemInterface $fileSystem,
        private Parser $parser,
    ) {}

    public function scan(string $directory, int $maxDepth = 3): array
    {
        $fqcns = [];

        if (! $this->fileSystem->isDirectory($directory)) {
            return $fqcns;
        }

        $this->scanDirectory($directory, $fqcns, 0, $maxDepth);

        return $fqcns;
    }

    private function scanDirectory(string $directory, array &$fqcns, int $currentDepth, int $maxDepth): void
    {
        if ($currentDepth > $maxDepth) {
            return;
        }

        $files = $this->fileSystem->glob($directory.'/*.php');

        foreach ($files as $file) {
            if (! $this->fileSystem->isFile($file)) {
                continue;
            }

            try {
                $content = $this->fileSystem->get($file);
                $classes = $this->analyzeFile($content);
                $fqcns = array_merge($fqcns, $classes);
            } catch (\Throwable $e) {
                continue;
            }
        }

        $subDirectories = $this->fileSystem->glob($directory.'/*', GLOB_ONLYDIR);

        foreach ($subDirectories as $subDirectory) {
            $this->scanDirectory($subDirectory, $fqcns, $currentDepth + 1, $maxDepth);
        }
    }

    /**
     * Analyzes a PHP file and returns all valid directive FQCNs.
     *
     * @param  string  $content  The PHP file content
     * @return array<int, string> List of fully qualified class names
     */
    private function analyzeFile(string $content): array
    {
        $foundClasses = [];

        try {
            $ast = $this->parser->parse($content);
            if ($ast === null) {
                return $foundClasses;
            }

            $visitor = new class extends NodeVisitorAbstract
            {
                public array $classes = [];

                public ?string $currentNamespace = null;

                public array $aliases = [];

                public function enterNode(Node $node): ?int
                {
                    if ($node instanceof Namespace_) {
                        $this->currentNamespace = $node->name !== null
                            ? $node->name->toString()
                            : null;

                        return null;
                    }

                    if ($node instanceof Use_) {
                        foreach ($node->uses as $use) {
                            $alias = $use->alias !== null
                                ? $use->alias->toString()
                                : $use->name->getLast();
                            $this->aliases[$alias] = $use->name->toString();
                        }

                        return null;
                    }

                    if ($node instanceof Class_) {
                        $className = $node->name->toString();
                        $isAbstract = $node->isAbstract();

                        $extendsAbstractDirective = false;
                        if ($node->extends !== null) {
                            $parentName = $node->extends->toString();
                            $extendsAbstractDirective = $this->isAbstractDirectiveParent($parentName);
                        }

                        if ($extendsAbstractDirective && ! $isAbstract && $this->currentNamespace !== null) {
                            $this->classes[] = $this->currentNamespace.'\\'.$className;
                        }

                        return null;
                    }

                    return null;
                }

                private function isAbstractDirectiveParent(string $parentName): bool
                {
                    if ($parentName === AbstractDirective::class) {
                        return true;
                    }

                    foreach ($this->aliases as $alias => $fqcn) {
                        if ($parentName === $alias && $fqcn === AbstractDirective::class) {
                            return true;
                        }
                    }

                    $parts = explode('\\', $parentName);

                    return end($parts) === 'AbstractDirective';
                }
            };

            $traverser = new NodeTraverser;
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            return $visitor->classes;
        } catch (Error $e) {
            return [];
        }
    }
}
