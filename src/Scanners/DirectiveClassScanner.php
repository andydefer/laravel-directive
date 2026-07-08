<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Scanners;

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
use PhpParser\ParserFactory;

final class DirectiveClassScanner implements DirectiveScannerInterface
{
    private Parser $parser;

    public function __construct(
        private readonly FileSystemInterface $fileSystem,
    ) {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

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
     * Analyse un fichier PHP et retourne tous les FQCN des directives valides.
     *
     * @return array<string> Liste des FQCN
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
                    // Capturer le namespace
                    if ($node instanceof Namespace_) {
                        $this->currentNamespace = $node->name !== null
                            ? $node->name->toString()
                            : null;

                        return null;
                    }

                    // Capturer les use (pour les alias)
                    if ($node instanceof Use_) {
                        foreach ($node->uses as $use) {
                            $alias = $use->alias !== null
                                ? $use->alias->toString()
                                : $use->name->getLast();
                            $this->aliases[$alias] = $use->name->toString();
                        }

                        return null;
                    }

                    // Analyser les classes
                    if ($node instanceof Class_) {
                        $className = $node->name->toString();
                        $isAbstract = $node->isAbstract();

                        // Vérifier l'héritage en tenant compte des alias
                        $extendsAbstractDirective = false;
                        if ($node->extends !== null) {
                            $parentName = $node->extends->toString();
                            $extendsAbstractDirective = $this->isAbstractDirectiveParent($parentName);
                        }

                        // Si c'est une directive valide, l'ajouter
                        if ($extendsAbstractDirective && ! $isAbstract && $this->currentNamespace !== null) {
                            $this->classes[] = $this->currentNamespace.'\\'.$className;
                        }

                        return null;
                    }

                    return null;
                }

                private function isAbstractDirectiveParent(string $parentName): bool
                {
                    // Vérifier avec le nom complet
                    if ($parentName === 'AndyDefer\\Directive\\AbstractDirective') {
                        return true;
                    }

                    // Vérifier avec les alias (use)
                    foreach ($this->aliases as $alias => $fqcn) {
                        if ($parentName === $alias && $fqcn === 'AndyDefer\\Directive\\AbstractDirective') {
                            return true;
                        }
                    }

                    // Vérifier avec le nom court (si use est utilisé)
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
