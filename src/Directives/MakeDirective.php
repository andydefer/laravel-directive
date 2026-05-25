<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Traits\FileCreator;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * Directive to generate new directive classes.
 */
final class MakeDirective extends AbstractDirective
{
    use FileCreator;

    private const DIRECTIVES_PATH = '/app/Directives/';
    private const BASE_NAMESPACE = 'App\\Directives';

    private string $stubPath;

    public function __construct(
        DirectiveInteractionService $interaction,
        private readonly SignatureValidationService $signatureValidator,
        private readonly DirectiveNamingService $namingService,
        ?string $stubPath = null,
    ) {
        parent::__construct($interaction);
        $this->initFileCreator();
        $this->stubPath = $stubPath ?? __DIR__ . '/../../stubs/directive.stub';
    }

    public function getSignature(): string
    {
        return 'make-directive {name}';
    }

    public function getDescription(): string
    {
        return 'Create a new directive class (supports subdirectories like "user/hello-directive")';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('create-directive');
        $aliases->add('make-cmd');

        return $aliases;
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');

        if ($name === null) {
            $this->showUsageError();

            return ExitCode::INVALID_ARGUMENT;
        }

        // Extraire le nom de base (sans chemin) pour la validation
        $baseName = basename($name);

        $validation = $this->signatureValidator->validate($baseName);

        if (! $validation->isValid) {
            $this->error($validation->error ?? 'Invalid directive name format');
            $this->line('');
            $this->line('Valid examples:');
            $this->line('  • user-create');
            $this->line('  • clean-log');
            $this->line('  • db-migrate-fresh');
            $this->line('  • user/domain/hello-directive (with subdirectories)');

            return ExitCode::INVALID_ARGUMENT;
        }

        // Extraire les segments du chemin
        $segments = $this->extractPathSegments($name);
        $className = $this->normalizeClassName($segments['className']);
        $subPath = $segments['subPath'];

        // Construire le namespace complet
        $namespace = $this->buildNamespace(self::BASE_NAMESPACE, $subPath);

        // Construire le chemin de destination
        $destinationPath = $this->getDestinationPath($subPath, $className);

        // Extraire la signature (dernier segment sans le suffixe Directive)
        $signature = $this->extractSignature($segments['className']);

        if (! $this->createFile($this->stubPath, $destinationPath, [
            '{{namespace}}' => $namespace,
            '{{signature}}' => $signature,
            '{{class}}' => $className,
            '{{description}}' => "Description for {$signature}",
            '{{date}}' => date('Y-m-d H:i:s'),
        ])) {
            return ExitCode::FAILURE;
        }

        $this->info('✅ Directive created successfully!');
        $this->line("   Class: {$namespace}\\{$className}");
        $this->line("   Path: {$destinationPath}");
        $this->line("   Signature: {$signature}");

        return ExitCode::SUCCESS;
    }

    /**
     * Extrait les segments d'un chemin comme "user/domain/hello-directive"
     */
    private function extractPathSegments(string $name): array
    {
        $segments = explode('/', $name);
        $className = array_pop($segments);

        // Mettre en majuscule la première lettre de chaque segment de dossier
        $subPath = !empty($segments) ? implode('/', array_map('ucfirst', $segments)) : '';

        return [
            'segments' => $segments,
            'className' => $className,
            'subPath' => $subPath,
        ];
    }

    /**
     * Normalise le nom de la classe:
     * - Convertit kebab-case en PascalCase
     * - Ajoute "Directive" si nécessaire (ne double pas)
     */
    private function normalizeClassName(string $className): string
    {
        // Convertir kebab-case en PascalCase
        $pascalCase = $this->toPascalCase($className);

        // Ajouter "Directive" seulement si ce n'est pas déjà le cas
        if (!str_ends_with($pascalCase, 'Directive')) {
            $pascalCase .= 'Directive';
        }

        return $pascalCase;
    }

    /**
     * Extrait la signature à partir du nom de classe
     * Ex: "HelloDirective" -> "hello" (converti en kebab-case)
     */
    private function extractSignature(string $className): string
    {
        // Enlever le suffixe "Directive" si présent
        $baseName = preg_replace('/Directive$/', '', $className);

        // Convertir PascalCase en kebab-case
        return $this->toKebabCase($baseName);
    }

    /**
     * Construit le chemin de destination complet
     */
    private function getDestinationPath(string $subPath, string $className): string
    {
        $baseDir = getcwd() . self::DIRECTIVES_PATH;

        if ($subPath) {
            $baseDir .= $subPath . '/';
            $this->ensureDirectoryExists($baseDir);
        }

        return $baseDir . $className . '.php';
    }

    /**
     * Surcharge de createFile pour utiliser un stub avec namespace
     */
    protected function createFile(string $stubPath, string $destinationPath, array $replacements, bool $force = false): bool
    {
        // Vérification d'existence
        if ($this->files->exists($destinationPath) && !$force) {
            $this->error("File already exists: {$destinationPath}");
            return false;
        }

        // Créer le répertoire si nécessaire
        $this->ensureDirectoryExists(dirname($destinationPath));

        // Lire le stub
        try {
            $stub = $this->files->get($stubPath);
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
            $this->error("Stub template not found at: {$stubPath}");
            return false;
        }

        // Remplacer les variables
        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );

        // Écrire le fichier
        if ($this->files->put($destinationPath, $content) === false) {
            $this->error("Cannot create file: {$destinationPath}");
            return false;
        }

        return true;
    }

    private function showUsageError(): void
    {
        $this->error('Directive name is required');
        $this->line('Usage: directive make-directive <name>');
        $this->line('Examples:');
        $this->line('  • directive make-directive user-create');
        $this->line('  • directive make-directive user/domain/hello-directive');
        $this->line('  • directive make-directive admin/user-list');
        $this->line('');
        $this->line('Use only letters, numbers, and hyphens. Must start with a letter.');
    }
}
