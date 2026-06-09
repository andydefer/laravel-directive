# FileCreatorService - Référence Technique

## Description

Service de création de fichiers à partir de templates (stubs) avec remplacement de variables. Il gère la création de répertoires, la lecture des stubs, le remplacement des placeholders et l'écriture des fichiers.

## Hiérarchie

```
FileCreatorService
    └── Aucune classe parente (classe finale)
    ├── Dépend de FileCreatorConfigInterface
    └── Dépend de FileSystemInterface
```

## Rôle principal

Ce service centralise la logique de création de fichiers dans un composant **stateless**, testable et découplé du framework. Contrairement au trait `FileCreator` déprécié, il injecte ses dépendances et utilise des objets typés (`ReplacementCollection`, `FileCreationContext`, `PermissionMode`).

## Prérequis

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `__construct(FileCreatorConfigInterface $config, ?FileSystemInterface $filesystem = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `FileCreatorConfigInterface` | Configuration des permissions et chemins |
| `$filesystem` | `FileSystemInterface|null` | Instance optionnelle (créée automatiquement) |

**Exemple :**
```php
$config = new FileCreatorConfig();
$service = new FileCreatorService($config);
```

---

### `createFile(string $stubPath, string $destinationPath, ReplacementCollection $replacements, FileCreationContext $context): FileCreationResultRecord`

Crée un fichier à partir d'un stub avec remplacement de variables.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$stubPath` | `string` | Chemin vers le fichier stub |
| `$destinationPath` | `string` | Chemin de destination absolu |
| `$replacements` | `ReplacementCollection` | Collection des paires placeholder → valeur |
| `$context` | `FileCreationContext` | Contexte pour l'état du traitement |

**Retourne :** `FileCreationResultRecord` - Résultat avec succès, chemin et message

**Exceptions :** Aucune (les erreurs sont capturées dans le contexte)

**Exemple :**
```php
$context = new FileCreationContext();
$replacements = new ReplacementCollection();
$replacements->addReplacement('{{name}}', 'UserTask');

$result = $service->createFile(
    '/stubs/task.stub',
    '/app/Tasks/UserTask.php',
    $replacements,
    $context
);

if ($result->success) {
    echo $result->message; // "File created successfully: /app/Tasks/UserTask.php"
}
```

---

### `createFileFromName(string $stubPath, string $name, string $baseDirectory, ReplacementCollection $replacements, FileCreationContext $context): FileCreationResultRecord`

Crée un fichier en construisant automatiquement le chemin de destination à partir d'un nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$stubPath` | `string` | Chemin vers le fichier stub |
| `$name` | `string` | Nom avec sous-répertoires (ex: "Admin/UserTask") |
| `$baseDirectory` | `string` | Répertoire de base |
| `$replacements` | `ReplacementCollection` | Collection des remplacements |
| `$context` | `FileCreationContext` | Contexte pour l'état |

**Retourne :** `FileCreationResultRecord` - Résultat de la création

**Exemple :**
```php
$result = $service->createFileFromName(
    '/stubs/class.stub',
    'Admin/UserTask',
    '/app/Directives',
    $replacements,
    $context
);
// Crée : /app/Directives/Admin/UserTask.php
```

---

### `toPascalCase(string $string, FileCreationContext $context): string`

Convertit une chaîne de kebab-case ou snake_case en PascalCase.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$string` | `string` | Chaîne en kebab-case ou snake_case |
| `$context` | `FileCreationContext` | Contexte pour logger la transformation |

**Retourne :** `string` - Chaîne en PascalCase

**Exemple :**
```php
$result = $service->toPascalCase('user-profile', $context); // 'UserProfile'
$result = $service->toPascalCase('user_profile', $context); // 'UserProfile'
```

---

### `toKebabCase(string $string, FileCreationContext $context): string`

Convertit une chaîne de PascalCase en kebab-case.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$string` | `string` | Chaîne en PascalCase |
| `$context` | `FileCreationContext` | Contexte pour logger la transformation |

**Retourne :** `string` - Chaîne en kebab-case

**Exemple :**
```php
$result = $service->toKebabCase('UserProfile', $context); // 'user-profile'
```

---

### `extractPathSegments(string $name, FileCreationContext $context): PathSegmentsRecord`

Extrait les segments d'un chemin et les convertit en PascalCase pour les sous-répertoires.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Chemin avec segments séparés par `/` |
| `$context` | `FileCreationContext` | Contexte pour stocker les segments |

**Retourne :** `PathSegmentsRecord` - Record contenant les segments, nom de classe, sous-chemin

**Exemple :**
```php
$segments = $service->extractPathSegments('admin/user/UserRepository', $context);

echo $segments->className;   // 'UserRepository'
echo $segments->subPath;     // 'Admin/User'
echo $segments->fullPath;    // 'Admin/User/UserRepository'
```

---

### `buildNamespace(string $baseNamespace, PathSegmentsRecord $segments, FileCreationContext $context): string`

Construit un namespace PHP à partir d'un namespace de base et des segments de chemin.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$baseNamespace` | `string` | Namespace de base (ex: "App\\Tasks") |
| `$segments` | `PathSegmentsRecord` | Segments extraits |
| `$context` | `FileCreationContext` | Contexte pour stocker le namespace |

**Retourne :** `string` - Namespace complet

**Exemple :**
```php
$namespace = $service->buildNamespace('App\\Tasks', $segments, $context);
// 'App\\Tasks\\Admin\\User'
```

---

### `getAppPath(string $baseDirectory, PathSegmentsRecord $segments, FileCreationContext $context): string`

Construit un chemin absolu pour un fichier.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$baseDirectory` | `string` | Répertoire de base |
| `$segments` | `PathSegmentsRecord` | Segments extraits |
| `$context` | `FileCreationContext` | Contexte pour stocker le chemin |

**Retourne :** `string` - Chemin absolu du fichier

**Exemple :**
```php
$path = $service->getAppPath('/app/Tasks/', $segments, $context);
// '/app/Tasks/Admin/UserTask.php'
```

---

## Cas d'utilisation

### Cas 1 : Création d'une directive depuis un stub

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contexts\FileCreationContext;

final class CreateDirectiveDirective extends AbstractDirective
{
    public function __construct(
        private readonly FileCreatorService $fileCreator
    ) {
        parent::__construct();
    }

    public function getSignature(): string
    {
        return 'make:directive {name}';
    }

    public function getDescription(): string
    {
        return 'Create a new directive class';
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $replacements = new ReplacementCollection();
        $replacements->addReplacement('{{class}}', $this->toPascalCase($name));
        $replacements->addReplacement('{{signature}}', $this->toKebabCase($name));

        $context = new FileCreationContext();
        $result = $this->fileCreator->createFileFromName(
            stubPath: __DIR__ . '/stubs/directive.stub',
            name: $name,
            baseDirectory: '/app/Directives',
            replacements: $replacements,
            context: $context
        );

        if (!$result->success) {
            $this->error($result->message);
            return ExitCode::FAILURE;
        }

        $this->info($result->message);
        return ExitCode::SUCCESS;
    }
}
```

### Cas 2 : Migration de l'ancien trait vers le service

```php
// ❌ Ancienne approche (trait déprécié)
use AndyDefer\Directive\Traits\FileCreator;

class OldDirective extends AbstractDirective
{
    use FileCreator;

    public function execute(): ExitCode
    {
        $this->initFileCreator();
        $this->createFile('/stub.stub', '/dest.php', ['{{name}}' => 'Value']);
        return ExitCode::SUCCESS;
    }
}

// ✅ Nouvelle approche (service injecté)
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contexts\FileCreationContext;

class NewDirective extends AbstractDirective
{
    public function __construct(
        private readonly FileCreatorService $fileCreator
    ) {
        parent::__construct();
    }

    public function execute(): ExitCode
    {
        $replacements = new ReplacementCollection();
        $replacements->addReplacement('{{name}}', 'Value');

        $context = new FileCreationContext();
        $result = $this->fileCreator->createFile(
            '/stub.stub',
            '/dest.php',
            $replacements,
            $context
        );

        if (!$result->success) {
            $this->error($result->message);
            return ExitCode::FAILURE;
        }

        return ExitCode::SUCCESS;
    }
}
```

---

## Flux d'exécution

```
createFile()
    │
    ├── 1. START → setCurrentStep()
    │
    ├── 2. Vérification existence fichier
    │       ├── Existe ET !force → FAILED
    │       └── Sinon → suite
    │
    ├── 3. CREATING_DIRECTORY → ensureDirectoryExists()
    │       └── makeDirectory() si inexistant
    │
    ├── 4. READING_STUB → getStubContent()
    │       ├── Succès → stubContent
    │       └── Échec → FAILED
    │
    ├── 5. REPLACING_VARIABLES → replaceVariables()
    │       └── str_replace() sur tous les placeholders
    │
    ├── 6. WRITING_FILE → writeFile()
    │       ├── Succès → suite
    │       └── Échec → FAILED
    │
    └── 7. COMPLETED → addCreatedFile()
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fichier existe déjà (force=false) | (aucune, erreur dans contexte) | `File already exists: {$path}` |
| Stub introuvable | `RuntimeException` capturée | `Stub template not found at: {$stubPath}` |
| Écriture échoue | (aucune, erreur dans contexte) | `Cannot create file: {$destinationPath}` |

---

## Intégration

### Déclaration dans un Service Provider

```php
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Services\FileSystemService;

public function register(): void
{
    $this->app->singleton(FileCreatorService::class, function ($app) {
        $config = new FileCreatorConfig();
        $filesystem = new FileSystemService();
        return new FileCreatorService($config, $filesystem);
    });
}
```

### Injection dans une Directive

```php
final class MyDirective extends AbstractDirective
{
    public function __construct(
        private readonly FileCreatorService $fileCreator
    ) {
        parent::__construct();
    }
}
```

---

## Performance

| Opération | Complexité |
|-----------|------------|
| `createFile()` | O(n) avec n = nombre de placeholders |
| `extractPathSegments()` | O(n) avec n = nombre de segments |
| `toPascalCase()` / `toKebabCase()` | O(m) avec m = longueur de la chaîne |
| `createStringCollection()` | O(n) avec n = nombre d'éléments |

- **Point important :** Le service est stateless, donc thread-safe et réutilisable
- La lecture du stub utilise `file_get_contents()` - pas de cache intégré

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |

| Dépendance | Version |
|------------|---------|
| `andydefer/domain-structures` | ^2.0 |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contexts\FileCreationContext;
use AndyDefer\Directive\Enums\PermissionMode;
use AndyDefer\Directive\Services\FileSystemService;

// 1. Configuration
$config = new FileCreatorConfig();
$filesystem = new FileSystemService();
$service = new FileCreatorService($config, $filesystem);

// 2. Préparer les remplacements
$replacements = new ReplacementCollection();
$replacements->addReplacement('{{class}}', 'UserTask');
$replacements->addReplacement('{{namespace}}', 'App\\Tasks');
$replacements->addReplacement('{{description}}', 'Handle user tasks');

// 3. Créer le contexte
$context = new FileCreationContext(force: false);

// 4. Créer le fichier
$result = $service->createFile(
    stubPath: __DIR__ . '/stubs/task.stub',
    destinationPath: __DIR__ . '/output/UserTask.php',
    replacements: $replacements,
    context: $context
);

// 5. Vérifier le résultat
if ($result->success) {
    echo "✅ " . $result->message . PHP_EOL;

    // Inspecter le contexte
    echo "Steps executed: " . $context->getStepsExecuted() . PHP_EOL;
    echo "Files created: " . $context->getCreatedFilesCount() . PHP_EOL;

    $logs = $context->getTransformationLogs();
    foreach ($logs as $log) {
        echo "Log: {$log}" . PHP_EOL;
    }
} else {
    echo "❌ " . $result->message . PHP_EOL;
    echo "Error: " . $context->getErrorMessage() . PHP_EOL;
}
```
---