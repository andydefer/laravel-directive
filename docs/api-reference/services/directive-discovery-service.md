# DirectiveDiscoveryService - Référence Technique

## Description

Service de découverte et de gestion des classes de directives à partir de multiples sources. Analyse les répertoires, les packages vendors et les sources personnalisées pour trouver les classes étendant `AbstractDirective`.

## Hiérarchie / Implémentations

```
DirectiveDiscoveryInterface
    └── DirectiveDiscoveryService
        └── DirectiveKernel (hérite de cette classe)
```

## Rôle principal

`DirectiveDiscoveryService` est le moteur de découverte du système de directives. Il permet de :

- Découvrir automatiquement les directives depuis 4 sources différentes (built-in, workspace, vendor, custom)
- Filtrer les directives par namespace, préfixe, source ou signature
- Gérer les signatures réservées (empêcher l'écrasement des commandes système)
- Ajouter manuellement des directives via leur FQCN
- Configurer la profondeur de scan des répertoires
- Suivre les problèmes rencontrés lors de la découverte

## Installation

```bash
composer require andydefer/laravel-directive
```

### Dépendances

- `Container` - Conteneur de dépendances
- `DirectiveConfigInterface` - Configuration du package
- `DirectiveScannerInterface` - Scan des classes PHP
- `DirectiveParserInterface` - Parsing des signatures
- `FileSystemInterface` - Opérations sur le système de fichiers
- PHP 8.1+

## API / Méthodes publiques

### `static init(Container $container): self`

Initialise le service de découverte avec un conteneur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$container` | `Container` | Conteneur de dépendances |

**Retourne :** `self` - Instance du service

**Exemple :**
```php
$container = DirectiveContainer::create();
$discovery = DirectiveDiscoveryService::init($container);
```

---

### `discover(): DirectiveMetadataCollection`

Découvre toutes les directives disponibles depuis toutes les sources.

**Retourne :** `DirectiveMetadataCollection` - Collection des métadonnées découvertes

**Exceptions :** Aucune (les erreurs sont capturées et ajoutées comme problèmes)

**Exemple :**
```php
$directives = $discovery->discover();

foreach ($directives as $directive) {
    echo $directive->class . ' - ' . $directive->signature . "\n";
}
```

---

### `addSource(string $directory): self`

Ajoute un répertoire source personnalisé à scanner.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directory` | `string` | Chemin absolu du répertoire |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$discovery->addSource(__DIR__ . '/src/Commands');
```

---

### `addSources(array $directories): self`

Ajoute plusieurs répertoires sources personnalisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directories` | `array<int, string>` | Liste des chemins |

**Retourne :** `self` - Instance fluide

---

### `addDirective(string $class, bool $force = false): self`

Ajoute une directive manuellement par son nom de classe complet.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `class-string<AbstractDirective>` | Nom de classe complet |
| `$force` | `bool` | Ignorer les signatures réservées |

**Retourne :** `self` - Instance fluide

**Exceptions :** `InvalidArgumentException` - Si la classe n'étend pas `AbstractDirective`

**Exemple :**
```php
$discovery->addDirective(MyCustomDirective::class);
```

---

### `addDirectives(array $classes, bool $force = false): self`

Ajoute plusieurs directives manuellement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$classes` | `array<class-string<AbstractDirective>>` | Liste des classes |
| `$force` | `bool` | Ignorer les signatures réservées |

**Retourne :** `self` - Instance fluide

---

### `setMaxDepth(int $depth): self`

Définit la profondeur maximale de scan des répertoires.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$depth` | `int` | Profondeur (entre 2 et 7) |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$discovery->setMaxDepth(5); // Scan jusqu'à 5 niveaux
```

---

### `getMaxDepth(): int`

Retourne la profondeur maximale de scan.

**Retourne :** `int` - Profondeur actuelle

---

### `ignoreSource(DiscoverySource|string $source): self`

Ignore une source spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$source` | `DiscoverySource\|string` | Source à ignorer |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
use AndyDefer\Directive\Enums\DiscoverySource;

$discovery->ignoreSource(DiscoverySource::VENDOR);
// Désactive la découverte des vendors
```

---

### `ignoreSources(array $sources): self`

Ignore plusieurs sources.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sources` | `array<DiscoverySource\|string>` | Sources à ignorer |

**Retourne :** `self` - Instance fluide

---

### `ignorePath(string $path): self`

Ignore un chemin spécifique lors du scan.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Chemin à ignorer |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$discovery->ignorePath(__DIR__ . '/src/Deprecated');
```

---

### `ignoreDirective(string $signature): self`

Ignore une directive par sa signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature complète ou partielle |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$discovery->ignoreDirective('test-directive');
// Ignore toutes les directives commençant par "test-directive"
```

---

### `onlyNamespace(string $namespace): self`

Restreint la découverte à un namespace spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à inclure |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$discovery->onlyNamespace('App\\Commands\\');
// Seulement les directives dans App\Commands
```

---

### `excludeNamespace(string $namespace): self`

Exclut un namespace de la découverte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à exclure |

**Retourne :** `self` - Instance fluide

---

### `onlyPrefix(string $prefix): self`

Restreint la découverte aux signatures commençant par un préfixe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Préfixe à inclure |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$discovery->onlyPrefix('app:');
// Seulement les directives commençant par "app:"
```

---

### `excludePrefix(string $prefix): self`

Exclut les signatures commençant par un préfixe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Préfixe à exclure |

**Retourne :** `self` - Instance fluide

---

### `disableAutoDiscovery(): self`

Désactive la découverte automatique (seules les directives enregistrées manuellement sont utilisées).

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$discovery->disableAutoDiscovery();
// Seules les directives ajoutées via addDirective() seront disponibles
```

---

### `manualOnly(): self`

Alias de `disableAutoDiscovery()`.

---

### `resetConfig(): self`

Réinitialise tous les filtres à leurs valeurs par défaut.

**Retourne :** `self` - Instance fluide

---

### `addProblem(string $key, string $context, string $message, array $contextData = []): void`

Ajoute un problème à la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Identifiant unique du problème |
| `$context` | `string` | Description du contexte |
| `$message` | `string` | Message d'erreur |
| `$contextData` | `array<string, mixed>` | Données contextuelles additionnelles |

**Retourne :** `void`

---

### `getProblems(): ListCollection`

Retourne la collection des problèmes rencontrés.

**Retourne :** `ListCollection` - Collection des problèmes

**Exemple :**
```php
$problems = $discovery->getProblems();
foreach ($problems as $problem) {
    echo $problem->get('message') . "\n";
}
```

---

### `clearProblems(): self`

Efface tous les problèmes.

**Retourne :** `self` - Instance fluide

---

## Cas d'utilisation

### Cas 1 : Découverte complète avec tous les filtres

```php
<?php

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Enums\DiscoverySource;

$container = DirectiveContainer::create();
$discovery = DirectiveDiscoveryService::init($container);

// Configuration de la découverte
$discovery
    ->setMaxDepth(4)
    ->addSource(__DIR__ . '/src/Commands')
    ->ignoreSource(DiscoverySource::VENDOR) // Ignore les vendors
    ->ignorePath(__DIR__ . '/src/Deprecated')
    ->onlyNamespace('App\\Commands\\')
    ->excludePrefix('test:');

// Découverte
$directives = $discovery->discover();

echo "Found " . $directives->count() . " directives\n";
foreach ($directives as $directive) {
    echo "- {$directive->signature} ({$directive->class})\n";
}
```

### Cas 2 : Ajout manuel de directives

```php
<?php

$discovery = DirectiveDiscoveryService::init($container);

// Ajout manuel
$discovery->addDirective(CustomDirective::class);
$discovery->addDirectives([
    AdminDirective::class,
    UserDirective::class,
    ReportDirective::class,
]);

// Découverte manuelle uniquement
$discovery->disableAutoDiscovery();
$directives = $discovery->discover();

echo "Only manual directives: " . $directives->count() . "\n";
```

### Cas 3 : Filtrage avancé par namespace et préfixe

```php
<?php

$discovery = DirectiveDiscoveryService::init($container);

// Inclure seulement les directives de l'API
$discovery
    ->onlyNamespace('App\\Api\\')
    ->onlyPrefix('api:');

$directives = $discovery->discover();
// Ne retourne que les directives dans App\Api commençant par "api:"

// Exclure certaines catégories
$discovery
    ->excludeNamespace('App\\Deprecated\\')
    ->excludePrefix('legacy:');

$directives = $discovery->discover();
// Exclut les directives dépréciées
```

### Cas 4 : Gestion des signatures réservées

```php
<?php

// Ajouter une signature réservée
$discovery->addReservedSignature('help');

// Une directive avec la signature 'help' sera ignorée (sauf si force=true)
$discovery->addDirective(CustomHelpDirective::class); // Ignoré

// Forcer l'ajout
$discovery->addDirective(CustomHelpDirective::class, true); // Ajouté

// Supprimer une signature réservée
$discovery->removeReservedSignature('help');
$discovery->addDirective(CustomHelpDirective::class); // Maintenant accepté
```

### Cas 5 : Suivi des problèmes de découverte

```php
<?php

$discovery = DirectiveDiscoveryService::init($container);

// Tenter de découvrir avec des sources invalides
$discovery->addSource('/invalid/path');
$discovery->addSource('/another/invalid/path');

$directives = $discovery->discover();

// Récupérer les problèmes
$problems = $discovery->getProblems();

if ($problems->isNotEmpty()) {
    foreach ($problems as $problem) {
        echo "❌ " . $problem->get('key') . "\n";
        echo "   Context: " . $problem->get('context') . "\n";
        echo "   Message: " . $problem->get('message') . "\n";
    }
}
```

### Cas 6 : Utilisation dans un Kernel

```php
<?php

use AndyDefer\Directive\DirectiveKernel;

$kernel = DirectiveKernel::init($container);

// Le kernel hérite de DirectiveDiscoveryService
$kernel
    ->addSource(__DIR__ . '/src/Commands')
    ->ignoreSource(DiscoverySource::VENDOR)
    ->onlyNamespace('App\\Commands\\');

// La découverte est déclenchée lors de l'exécution
$kernel->run($argv);
```

---

## Flux d'exécution

```
discover()
    ↓
Réinitialiser la collection
    ↓
discoverRegisteredDirectives()
    ├── Pour chaque directive enregistrée
    │   ├── valider la classe
    │   ├── vérifier les filtres
    │   └── ajouter à la collection
    ↓
Si auto-discovery enabled:
    ├── discoverBuiltInDirectives()
    │   └── BuiltInDirectiveDiscovery → FQCNs
    ├── discoverWorkspaceDirectives()
    │   └── WorkspaceDirectiveDiscovery → FQCNs
    ├── discoverVendorDirectives()
    │   └── VendorDirectiveDiscovery → FQCNs
    └── discoverCustomDirectives()
        └── Scanner → FQCNs par source
    ↓
Pour chaque FQCN:
    ├── isValidDirectiveClass()
    ├── vérifier ignoredDirectives
    ├── vérifier reservedSignatures
    ├── vérifier namespace filters
    ├── vérifier prefix filters
    └── ajouter à la collection
    ↓
Retourner DirectiveMetadataCollection (unique by class)
```

### Filtrage des FQCNs

```
addDirectiveFromFqcn($fqcn, $force)
    ↓
isValidDirectiveClass()
    ├── Non abstraite
    └── Étend AbstractDirective
    ↓
ignoredDirectives contient signature? → ignorer
    ↓
force=false ET isReservedSignature()? → ignorer
    ↓
passesNamespaceFilters()
    ├── Si onlyNamespaces → doit correspondre
    └── Si excludedNamespaces → ne doit pas correspondre
    ↓
passesPrefixFilters()
    ├── Si onlyPrefixes → signature doit correspondre
    └── Si excludedPrefixes → signature ne doit pas correspondre
    ↓
Ajouter à la collection
```

---

## Gestion des erreurs

### Système de problèmes

| Type de problème | Clé | Contexte | Données |
|------------------|-----|----------|---------|
| Échec de configuration | `config_loading` | `Failed to load custom sources from configuration` | `config_key` |
| Résolution de parser | `parser_resolution` | `Failed to resolve DirectiveParserInterface` | `fallback` |
| Résolution de scanner | `scanner_resolution` | `Failed to resolve DirectiveScannerInterface` | `fallback` |
| Résolution de filesystem | `filesystem_resolution` | `Failed to resolve FileSystemInterface` | `fallback` |
| Source personnalisée non valide | `custom_source_not_directory` | `Custom source path is not a directory` | `path` |
| Réflexion échouée | `reflection_error` | `Failed to reflect class` | `class` |
| Ajout de directive | `add_directive` | `Failed to add directive class` | `class`, `force` |
| Vérification de signature | `reserved_signature_check` | `Failed to check reserved signature` | `signature` |
| Découverte de source | `discover_{source}_source` | `Failed to discover {source} directives` | `source` |

### Aucune exception n'est levée lors de la découverte. Les erreurs sont capturées et ajoutées comme problèmes.

---

## Intégration

### Avec DirectiveContainer

```php
$container = DirectiveContainer::create('/path/to/project');
$discovery = DirectiveDiscoveryService::init($container);
```

### Avec Laravel (Service Provider)

```php
// Dans un ServiceProvider
$this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
    $discovery = DirectiveDiscoveryService::init($app->make(Container::class));
    
    // Configuration depuis config/directive.php
    $config = config('directive');
    if (!empty($config['custom_sources'])) {
        $discovery->addSources($config['custom_sources']);
    }
    if (!empty($config['ignored_sources'])) {
        $discovery->ignoreSources($config['ignored_sources']);
    }
    if (!empty($config['max_depth'])) {
        $discovery->setMaxDepth($config['max_depth']);
    }
    
    return $discovery;
});
```

### Avec DirectiveKernel

```php
// Le kernel hérite de DirectiveDiscoveryService
$kernel = DirectiveKernel::init($container);
$kernel
    ->addSource(__DIR__ . '/src/Directives')
    ->onlyNamespace('App\\Directives\\')
    ->ignoreSource(DiscoverySource::VENDOR);

// La configuration est utilisée lors de l'exécution
$kernel->run($argv);
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `discover()` | O(n × d) | n = classes PHP, d = profondeur de scan |
| `addDirective()` | O(1) | Ajout dans une collection |
| `ignoreSource()` | O(1) | Ajout dans une collection |
| `onlyNamespace()` | O(1) | Ajout dans une collection |

**Optimisations :**
- Les directives enregistrées ont priorité
- Les résultats sont dédoublonnés par classe
- Les filtres sont évalués avant l'instanciation des classes
- Cache des directives dans le kernel

**Mémoire :**
- Toutes les métadonnées sont stockées dans une collection
- Les classes sont instanciées sans constructeur (si possible)
- Les FQCNs sont conservés en mémoire

---

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.4 | ✅ Complet | Support total |
| PHP 8.3 | ✅ Complet | Support total |
| PHP 8.2 | ✅ Complet | Support total |
| PHP 8.1 | ✅ Complet | Support total |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Enums\DiscoverySource;

// 1. Création du conteneur
$container = DirectiveContainer::create(__DIR__);

// 2. Initialisation du service
$discovery = DirectiveDiscoveryService::init($container);

// 3. Configuration
$discovery
    // Profondeur de scan
    ->setMaxDepth(4)
    
    // Sources personnalisées
    ->addSource(__DIR__ . '/src/Directives')
    ->addSource(__DIR__ . '/app/Commands')
    
    // Ignorer certaines sources
    ->ignoreSource(DiscoverySource::VENDOR)
    ->ignoreSource(DiscoverySource::BUILTIN)
    
    // Ignorer des chemins spécifiques
    ->ignorePath(__DIR__ . '/src/Deprecated')
    ->ignorePath(__DIR__ . '/tests')
    
    // Ignorer certaines directives
    ->ignoreDirective('test:legacy')
    ->ignoreDirective('deprecated:')
    
    // Filtrage par namespace
    ->onlyNamespace('App\\Directives\\')
    ->excludeNamespace('App\\Directives\\Internal\\')
    
    // Filtrage par préfixe
    ->onlyPrefix('app:')
    ->excludePrefix('admin:');

// 4. Découverte
$directives = $discovery->discover();

echo "=== Discovery Report ===\n";
echo "Total directives found: " . $directives->count() . "\n\n";

// 5. Analyse des résultats
$classes = [];
foreach ($directives as $directive) {
    $classes[] = $directive->class;
    echo "- {$directive->signature}\n";
    echo "  Class: {$directive->class}\n";
    echo "  Description: {$directive->description}\n";
    if ($directive->aliases->isNotEmpty()) {
        echo "  Aliases: " . implode(', ', $directive->aliases->toArray()) . "\n";
    }
    echo "\n";
}

// 6. Dédoublonnage
$unique = $directives->uniqueByClass();
echo "Unique classes: " . $unique->count() . "\n\n";

// 7. Récupération des problèmes
$problems = $discovery->getProblems();
if ($problems->isNotEmpty()) {
    echo "=== Problems Encountered ===\n";
    foreach ($problems as $problem) {
        echo "  ❌ " . $problem->get('key') . "\n";
        echo "     Context: " . $problem->get('context') . "\n";
        echo "     Message: " . $problem->get('message') . "\n";
    }
    echo "\n";
}

// 8. Ajout manuel d'une directive
try {
    $discovery->addDirective(MyCustomDirective::class);
    echo "✅ Manual directive added\n";
} catch (InvalidArgumentException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// 9. Forcer l'ajout (ignore réservé)
$discovery->addDirective(MyHelpDirective::class, true);
echo "✅ Forced directive added\n";

// 10. Réinitialisation pour un nouveau scan
$discovery->resetConfig()->manualOnly();
echo "\nConfig reset, manual only\n";

$manualDirectives = $discovery->discover();
echo "Manual directives: " . $manualDirectives->count() . "\n";

// 11. Vérification des signatures réservées
$reserved = $discovery->getReservedSignatures();
echo "\nReserved signatures:\n";
foreach ($reserved as $signature) {
    echo "  - $signature\n";
}
```

## Voir aussi

- `DirectiveKernel` - Noyau d'exécution (hérite de cette classe)
- `DirectiveMetadataCollection` - Collection des métadonnées
- `DiscoverySource` - Énumération des sources
- `BuiltInDirectiveDiscovery` - Découverte des directives intégrées
- `WorkspaceDirectiveDiscovery` - Découverte dans l'espace de travail
- `VendorDirectiveDiscovery` - Découverte dans les vendors
- `AbstractDiscovery` - Classe de base pour les sources de découverte