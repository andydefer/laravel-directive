# DirectiveDiscoveryService - Référence Technique

## Description

`DirectiveDiscoveryService` est le service central de découverte des directives. Il scrute différentes sources (intégrées, espace de travail, vendors, sources personnalisées) pour trouver et enregistrer toutes les directives disponibles, tout en appliquant des filtres de namespace, de préfixe et d'ignorance.

## Hiérarchie / Implémentations

```
DirectiveDiscoveryInterface
    └── DirectiveDiscoveryService
            └── DirectiveKernel (étend cette classe)
```

**Interfaces implémentées :** `DirectiveDiscoveryInterface`

## Rôle principal

`DirectiveDiscoveryService` agit comme le moteur de découverte des directives. Il assure :

- La **découverte** des directives depuis 5 sources différentes
- Le **filtrage** par namespace, préfixe, chemin et signature
- La **gestion des problèmes** rencontrés lors de la découverte
- L'**ajout manuel** de directives via FQCN
- La **configuration** de la profondeur de scan et des sources ignorées
- La **collecte des métadonnées** des directives (signature, description, alias)

---

## API / Méthodes publiques

### `init(Application $container): static`

Initialise le service de découverte avec un conteneur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$container` | `Application` | Instance de l'application Laravel |

**Retourne :** `static` - Instance du service

**Exemple :**
```php
$discovery = DirectiveDiscoveryService::init($app);
```

---

### `setMaxDepth(int $depth): static`

Définit la profondeur maximale de scan des répertoires.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$depth` | `int` | Profondeur (clampée entre 2 et 7) |

**Retourne :** `static` - Instance du service (fluent)

**Exemple :**
```php
$discovery->setMaxDepth(5);
```

---

### `getMaxDepth(): int`

Retourne la profondeur maximale de scan.

**Retourne :** `int` - Profondeur actuelle

---

### `addProblem(string $key, string $context, string $message, mixed $contextData = [], int $backtraceOffset = 1): void`

Ajoute un problème à la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Identifiant unique du problème |
| `$context` | `string` | Description lisible du contexte |
| `$message` | `string` | Message d'erreur |
| `$contextData` | `mixed` | Données contextuelles supplémentaires |
| `$backtraceOffset` | `int` | Décalage dans la stack trace |

**Exemple :**
```php
$discovery->addProblem(
    'config_loading',
    'Failed to load custom sources',
    'Configuration file not found',
    ['config_file' => '/path/to/config.php']
);
```

---

### `getProblems(): ListCollection`

Retourne tous les problèmes rencontrés.

**Retourne :** `ListCollection<MapCollection>` - Collection des problèmes

---

### `clearProblems(): static`

Efface tous les problèmes.

**Retourne :** `static` - Instance du service (fluent)

---

### `ignoreSource(DiscoverySource|string $source): static`

Ignore une source de découverte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$source` | `DiscoverySource|string` | Source à ignorer |

**Retourne :** `static` - Instance du service (fluent)

**Exemple :**
```php
$discovery->ignoreSource(DiscoverySource::VENDOR);
// ou
$discovery->ignoreSource('vendor');
```

---

### `ignoreSources(array $sources): static`

Ignore plusieurs sources.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sources` | `array<DiscoverySource|string>` | Sources à ignorer |

**Retourne :** `static` - Instance du service (fluent)

---

### `enableSource(DiscoverySource|string $source): static`

Réactive une source précédemment ignorée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$source` | `DiscoverySource|string` | Source à réactiver |

**Retourne :** `static` - Instance du service (fluent)

---

### `enableSources(array $sources): static`

Réactive plusieurs sources.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sources` | `array<DiscoverySource|string>` | Sources à réactiver |

**Retourne :** `static` - Instance du service (fluent)

---

### `isSourceIgnored(DiscoverySource|string $source): bool`

Vérifie si une source est ignorée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$source` | `DiscoverySource|string` | Source à vérifier |

**Retourne :** `bool` - `true` si ignorée, `false` sinon

---

### `ignorePath(string $path): static`

Ignore un chemin de scan.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Chemin à ignorer |

**Retourne :** `static` - Instance du service (fluent)

---

### `ignorePaths(array $paths): static`

Ignore plusieurs chemins.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$paths` | `array<string>` | Chemins à ignorer |

**Retourne :** `static` - Instance du service (fluent)

---

### `enablePath(string $path): static`

Réactive un chemin précédemment ignoré.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Chemin à réactiver |

**Retourne :** `static` - Instance du service (fluent)

---

### `enablePaths(array $paths): static`

Réactive plusieurs chemins.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$paths` | `array<string>` | Chemins à réactiver |

**Retourne :** `static` - Instance du service (fluent)

---

### `ignoreDirective(string $signature): static`

Ignore une directive par sa signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la directive |

**Retourne :** `static` - Instance du service (fluent)

**Exemple :**
```php
$discovery->ignoreDirective('greet');
```

---

### `ignoreDirectives(array $signatures): static`

Ignore plusieurs directives.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signatures` | `array<string>` | Signatures à ignorer |

**Retourne :** `static` - Instance du service (fluent)

---

### `enableDirective(string $signature): static`

Réactive une directive précédemment ignorée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à réactiver |

**Retourne :** `static` - Instance du service (fluent)

---

### `enableDirectives(array $signatures): static`

Réactive plusieurs directives.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signatures` | `array<string>` | Signatures à réactiver |

**Retourne :** `static` - Instance du service (fluent)

---

### `isDirectiveIgnored(string $signature): bool`

Vérifie si une directive est ignorée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à vérifier |

**Retourne :** `bool` - `true` si ignorée, `false` sinon

---

### `onlyNamespace(string $namespace): static`

Ajoute un namespace à la liste des namespaces autorisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à autoriser |

**Retourne :** `static` - Instance du service (fluent)

**Exemple :**
```php
$discovery->onlyNamespace('App\\Directives\\');
```

---

### `onlyNamespaces(array $namespaces): static`

Ajoute plusieurs namespaces à la liste des namespaces autorisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespaces` | `array<string>` | Namespaces à autoriser |

**Retourne :** `static` - Instance du service (fluent)

---

### `excludeNamespace(string $namespace): static`

Exclut un namespace.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à exclure |

**Retourne :** `static` - Instance du service (fluent)

---

### `excludeNamespaces(array $namespaces): static`

Exclut plusieurs namespaces.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespaces` | `array<string>` | Namespaces à exclure |

**Retourne :** `static` - Instance du service (fluent)

---

### `onlyPrefix(string $prefix): static`

Ajoute un préfixe à la liste des préfixes autorisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Préfixe à autoriser |

**Retourne :** `static` - Instance du service (fluent)

**Exemple :**
```php
$discovery->onlyPrefix('test:');
// Seules les directives commençant par 'test:' seront découvertes
```

---

### `onlyPrefixes(array $prefixes): static`

Ajoute plusieurs préfixes à la liste des préfixes autorisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefixes` | `array<string>` | Préfixes à autoriser |

**Retourne :** `static` - Instance du service (fluent)

---

### `excludePrefix(string $prefix): static`

Exclut un préfixe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Préfixe à exclure |

**Retourne :** `static` - Instance du service (fluent)

---

### `excludePrefixes(array $prefixes): static`

Exclut plusieurs préfixes.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefixes` | `array<string>` | Préfixes à exclure |

**Retourne :** `static` - Instance du service (fluent)

---

### `disableAutoDiscovery(): static`

Désactive la découverte automatique.

**Retourne :** `static` - Instance du service (fluent)

---

### `enableAutoDiscovery(): static`

Active la découverte automatique.

**Retourne :** `static` - Instance du service (fluent)

---

### `manualOnly(): static`

Alias pour `disableAutoDiscovery()`.

**Retourne :** `static` - Instance du service (fluent)

---

### `isAutoDiscoveryEnabled(): bool`

Vérifie si la découverte automatique est activée.

**Retourne :** `bool` - `true` si activée, `false` sinon

---

### `resetConfig(): static`

Réinitialise tous les filtres à leurs valeurs par défaut.

**Retourne :** `static` - Instance du service (fluent)

---

### `addSource(string $directory): static`

Ajoute un répertoire source personnalisé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directory` | `string` | Chemin du répertoire à scanner |

**Retourne :** `static` - Instance du service (fluent)

**Exemple :**
```php
$discovery->addSource('/path/to/custom/directives');
```

---

### `addSources(array $directories): static`

Ajoute plusieurs répertoires sources personnalisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directories` | `array<string>` | Chemins des répertoires à scanner |

**Retourne :** `static` - Instance du service (fluent)

---

### `addDirective(string $class, bool $force = false): static`

Ajoute une directive directement par sa classe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `class-string<AbstractDirective>` | Classe de la directive |
| `$force` | `bool` | Forcer même si la signature est réservée |

**Retourne :** `static` - Instance du service (fluent)

**Exceptions :** `InvalidArgumentException` si la classe n'étend pas `AbstractDirective`

**Exemple :**
```php
$discovery->addDirective(GreetDirective::class);
```

---

### `addDirectives(array $classes, bool $force = false): static`

Ajoute plusieurs directives directement par leurs classes.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$classes` | `array<class-string<AbstractDirective>>` | Classes des directives |
| `$force` | `bool` | Forcer même si les signatures sont réservées |

**Retourne :** `static` - Instance du service (fluent)

---

### `discover(): DirectiveMetadataCollection`

Découvre toutes les directives disponibles depuis toutes les sources.

**Retourne :** `DirectiveMetadataCollection` - Collection des métadonnées des directives

**Sources découvertes dans l'ordre :**
1. Directives enregistrées manuellement
2. Directives intégrées (Built-in)
3. Directives de l'espace de travail (Workspace)
4. Directives des vendors
5. Sources personnalisées

**Exemple :**
```php
$directives = $discovery->discover();
foreach ($directives as $directive) {
    echo $directive->signature . "\n";
}
```

---

### `getCollection(): DirectiveMetadataCollection`

Retourne la collection actuelle sans relancer la découverte.

**Retourne :** `DirectiveMetadataCollection` - Collection actuelle

---

### `clear(): void`

Efface la collection.

**Exemple :**
```php
$discovery->clear();
// La collection est maintenant vide
```

---

### `addReservedSignature(string $signature): static`

Ajoute une signature réservée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à réserver |

**Retourne :** `static` - Instance du service (fluent)

---

### `removeReservedSignature(string $signature): static`

Supprime une signature réservée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à libérer |

**Retourne :** `static` - Instance du service (fluent)

---

### `getReservedSignatures(): array`

Retourne toutes les signatures réservées.

**Retourne :** `array<string>` - Liste des signatures réservées

---

## Cas d'utilisation

### Cas 1 : Découverte de base

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

$discovery = DirectiveDiscoveryService::init($app);

// Ajouter une source personnalisée
$discovery->addSource('/path/to/custom/directives');

// Découvrir toutes les directives
$directives = $discovery->discover();

echo "Found " . $directives->count() . " directives\n";
foreach ($directives as $directive) {
    echo "  - {$directive->signature}\n";
}
```

---

### Cas 2 : Filtrage par namespace

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

$discovery = DirectiveDiscoveryService::init($app);

// Uniquement les directives du namespace App\Directives\
$discovery->onlyNamespace('App\\Directives\\');

// Exclure le namespace App\Directives\Tests\
$discovery->excludeNamespace('App\\Directives\\Tests\\');

$directives = $discovery->discover();
// Seules les directives de App\Directives\ (hors Tests\) sont incluses
```

---

### Cas 3 : Filtrage par préfixe

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

$discovery = DirectiveDiscoveryService::init($app);

// Uniquement les directives commençant par 'test:'
$discovery->onlyPrefix('test:');

// Exclure les directives commençant par 'test:internal'
$discovery->excludePrefix('test:internal');

$directives = $discovery->discover();
// Seules les directives test: (hors test:internal) sont incluses
```

---

### Cas 4 : Ignorer des sources

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Enums\DiscoverySource;

$discovery = DirectiveDiscoveryService::init($app);

// Ignorer les directives des vendors
$discovery->ignoreSource(DiscoverySource::VENDOR);

// Ignorer plusieurs sources
$discovery->ignoreSources([
    DiscoverySource::VENDOR,
    DiscoverySource::CUSTOM,
]);

$directives = $discovery->discover();
// Seules les sources non ignorées sont scannées
```

---

### Cas 5 : Ajout manuel de directives

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use App\Directives\GreetDirective;
use App\Directives\DeployDirective;

$discovery = DirectiveDiscoveryService::init($app);

// Ajout manuel avec force (ignore les signatures réservées)
$discovery->addDirective(GreetDirective::class, true);

// Ajout de plusieurs directives
$discovery->addDirectives([
    DeployDirective::class,
    BackupDirective::class,
]);

// La découverte inclut ces directives
$directives = $discovery->discover();
```

---

### Cas 6 : Gestion des problèmes

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

$discovery = DirectiveDiscoveryService::init($app);

// Ajout d'un chemin inexistant
$discovery->addSource('/invalid/path');

// La découverte va générer des problèmes
$directives = $discovery->discover();

// Récupérer et afficher les problèmes
$problems = $discovery->getProblems();
foreach ($problems as $problem) {
    echo "Key: " . $problem->get('key') . "\n";
    echo "Context: " . $problem->get('context') . "\n";
    echo "Message: " . $problem->get('message') . "\n";
    echo "---\n";
}

// Nettoyer les problèmes
$discovery->clearProblems();
```

---

## Flux d'exécution

```
discover()
    ↓
collection = new DirectiveMetadataCollection
    ↓
1. discoverRegisteredDirectives()
    └── addDirectiveFromFqcn() pour chaque classe enregistrée
    ↓
2. discoverBuiltInDirectives()
    ├── BuiltInDirectiveDiscovery->discover()
    └── addDirectiveFromFqcn() pour chaque FQCN trouvé
    ↓
3. discoverWorkspaceDirectives()
    ├── WorkspaceDirectiveDiscovery->discover()
    └── addDirectiveFromFqcn() pour chaque FQCN trouvé
    ↓
4. discoverVendorDirectives()
    ├── VendorDirectiveDiscovery->discover()
    └── addDirectiveFromFqcn() pour chaque FQCN trouvé
    ↓
5. discoverCustomDirectives()
    ├── Pour chaque source personnalisée
    │   ├── Scanner->scan($directory, $maxDepth)
    │   └── addDirectiveFromFqcn() pour chaque FQCN trouvé
    └──
    ↓
addDirectiveFromFqcn($fqcn)
    ├── ReflectionClass($fqcn)
    ├── isValidDirectiveClass() (vérifie que c'est un AbstractDirective)
    ├── Vérifie si la signature est ignorée
    ├── Vérifie si la signature est réservée (sauf force=true)
    ├── passesNamespaceFilters()
    ├── passesPrefixFilters()
    └── Ajoute à la collection
    ↓
Retourne DirectiveMetadataCollection (uniqueByClass)
```

---

## Gestion des erreurs

| Situation | Problème | Contexte | Message |
|-----------|----------|----------|---------|
| Échec de chargement des sources config | `config_loading` | `Failed to load custom sources from configuration` | Message de l'exception |
| Résolution du parser échouée | `parser_resolution` | `Failed to resolve DirectiveParserInterface from container, using fallback` | Message de l'exception |
| Résolution du scanner échouée | `scanner_resolution` | `Failed to resolve DirectiveScannerInterface from container, using fallback` | Message de l'exception |
| Résolution du filesystem échouée | `filesystem_resolution` | `Failed to resolve FileSystemInterface from container, using fallback` | Message de l'exception |
| Résolution de la config échouée | `config_resolution` | `Failed to resolve DirectiveConfigInterface from container` | Message de l'exception |
| Échec découverte built-in | `discover_builtin` | `Failed to discover built-in directives` | Message de l'exception |
| Échec découverte workspace | `discover_workspace` | `Failed to discover workspace directives` | Message de l'exception |
| Échec découverte vendor | `discover_vendor` | `Failed to discover vendor directives` | Message de l'exception |
| Échec découverte custom | `discover_custom` | `Failed to discover custom directives` | Message de l'exception |
| Classe invalide pour addDirective | `add_directive` | `Failed to add directive class: {class}` | `Class "{class}" must extend AbstractDirective` |
| Erreur de réflexion | `reflection_error` | `Failed to reflect class: {class}` | Message de l'exception |

---

## Performance

- **Découverte** : O(n) où n est le nombre de fichiers scannés
- **Scan des répertoires** : Profondeur configurable (default 3, max 7)
- **Filtrage** : O(1) par vérification (collections typées)
- **Mémoire** : Stocke les métadonnées des directives en mémoire
- **Problèmes** : Collection mutable, peut être nettoyée

### Optimisations

- Cache des directives découvertes
- Scan limité par profondeur max
- Filtrage avant instanciation complète
- Collections typées pour les performances

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.4 | ✅ Complet |
| PHP 8.3 | ✅ Complet |
| PHP 8.2 | ✅ Complet |
| PHP 8.1 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Enums\DiscoverySource;
use App\Directives\GreetDirective;
use App\Directives\DeployDirective;

// 1. Création de l'application
$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class
])->build();

// 2. Initialisation du service de découverte
$discovery = DirectiveDiscoveryService::init($app);

// 3. Configuration
$discovery->setMaxDepth(4)
    ->addSource('/custom/directives')
    ->addSource('/project/directives')
    ->onlyNamespace('App\\Directives\\')
    ->excludePrefix('test:')
    ->ignoreSource(DiscoverySource::VENDOR);

// 4. Ajout manuel de directives
$discovery->addDirectives([
    GreetDirective::class,
    DeployDirective::class,
], true); // force = true pour ignorer les signatures réservées

// 5. Découverte
$directives = $discovery->discover();

// 6. Affichage des résultats
echo "Discovered " . $directives->count() . " directives\n\n";

foreach ($directives as $directive) {
    echo "📦 " . $directive->signature . "\n";
    echo "   Class: " . $directive->class . "\n";
    echo "   Description: " . $directive->description . "\n";
    
    if ($directive->aliases->isNotEmpty()) {
        echo "   Aliases: " . implode(', ', $directive->aliases->toArray()) . "\n";
    }
    echo "\n";
}

// 7. Vérification des problèmes
$problems = $discovery->getProblems();
if (!$problems->isEmpty()) {
    echo "⚠️ Problems encountered:\n";
    foreach ($problems as $problem) {
        echo "  - [{$problem->get('key')}] " . $problem->get('context') . "\n";
        echo "    " . $problem->get('message') . "\n";
    }
}

// 8. Réinitialisation
$discovery->resetConfig()->clearProblems();
```

---

## Voir aussi

- `DirectiveDiscoveryInterface` - Interface du service
- `DirectiveMetadataRecord` - Enregistrement des métadonnées
- `DirectiveMetadataCollection` - Collection des métadonnées
- `DiscoverySource` - Énumération des sources
- `WorkspaceDirectiveDiscovery` - Découverte workspace
- `VendorDirectiveDiscovery` - Découverte vendor
- `BuiltInDirectiveDiscovery` - Découverte built-in