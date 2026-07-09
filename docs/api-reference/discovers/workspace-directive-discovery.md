# WorkspaceDirectiveDiscovery - Référence Technique

## Description

Source de découverte qui scanne les répertoires de l'application (workspace) pour trouver des directives définies par le développeur. Par défaut, elle recherche dans `src/Directives` et `app/Directives`, mais peut être configurée pour scanner des chemins personnalisés.

## Hiérarchie / Implémentations

```
DiscoverySourceInterface
    └── WorkspaceDirectiveDiscovery (final)
```

## Rôle principal

Permettre aux développeurs de créer leurs propres directives dans l'application sans configuration supplémentaire. La classe découvre automatiquement toutes les classes qui étendent `AbstractDirective` dans les dossiers configurés.

## Installation

Cette classe est utilisée automatiquement par le service de découverte. Aucune configuration manuelle n'est nécessaire.

### Configuration via le fichier de config

```php
// config/directive.php
return [
    'directories' => [
        'app/Directives',
        'src/Directives',
        'app/Console/Commands/Directives', // Dossier personnalisé
    ],
];
```

## API / Méthodes publiques

### `discover(): array`

Découvre toutes les directives présentes dans le workspace de l'application.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des noms de classes qualifiés (FQCN)

**Exceptions :** Aucune (les répertoires inexistants sont ignorés silencieusement)

**Exemple :**
```php
<?php

use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;

$discovery = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);
$directives = $discovery->discover();
// Retourne les directives trouvées dans src/Directives et app/Directives
```

---

### `addPath(string $path): self`

Ajoute un chemin personnalisé à scanner.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Chemin relatif à la racine du projet |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);
$discovery
    ->addPath('app/CustomDirectives')
    ->addPath('modules/Admin/Directives');
```

---

### `addPaths(array $paths): self`

Ajoute plusieurs chemins personnalisés à scanner.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$paths` | `array<int, string>` | Liste des chemins relatifs à la racine du projet |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery->addPaths([
    'app/CustomDirectives',
    'modules/Admin/Directives',
    'packages/Acme/Directives',
]);
```

## Cas d'utilisation

### Cas 1 : Structure de projet Laravel standard

```php
// Dossiers par défaut
// app/Directives/
//   UserDirective.php
//   AdminDirective.php

// src/Directives/
//   ApiDirective.php

// Découverte automatique
$discovery = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);
$directives = $discovery->discover();
// Retourne toutes les directives dans ces dossiers
```

### Cas 2 : Structure modulaire

```php
// Structure : app/Modules/Admin/Directives/
// app/Modules/
//   Admin/
//     Directives/
//       DashboardDirective.php
//     Commands/
//   Api/
//     Directives/
//       EndpointDirective.php

// Configuration
$discovery = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);
$discovery->addPaths([
    'app/Modules/Admin/Directives',
    'app/Modules/Api/Directives',
]);

$directives = $discovery->discover();
// Retourne DashboardDirective et EndpointDirective
```

### Cas 3 : Configuration via fichier config

```php
// config/directive.php
<?php

return [
    'directories' => [
        'app/Console/Directives',
        'app/Http/Directives',
        'modules/*/Directives', // Pattern glob (à développer)
    ],
];

// Dans le service provider
$config = app(DirectiveConfigInterface::class);
$discovery = new WorkspaceDirectiveDiscovery(
    $fileSystem,
    $scanner,
    $config // Utilise la configuration
);
```

### Cas 4 : Ajout dynamique lors de l'exécution

```php
<?php

use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;

class MyServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $discovery = $this->app->make(WorkspaceDirectiveDiscovery::class);
        
        // Ajouter des chemins basés sur les modules actifs
        if ($this->moduleExists('Admin')) {
            $discovery->addPath('modules/Admin/Directives');
        }
        
        if ($this->moduleExists('Api')) {
            $discovery->addPath('modules/Api/Directives');
        }
    }
}
```

## Flux d'exécution

```
WorkspaceDirectiveDiscovery::discover()
    │
    ├── Vérifie $cache
    │   └── Si cache présent → retourne
    │
    └── doDiscover()
        │
        ├── getProjectRoot()
        │   └── getcwd() (vérifié)
        │
        ├── getScanPaths()
        │   ├── DEFAULT_PATHS
        │   ├── Config::getDirectories() (si config présent)
        │   └── $customPaths
        │
        └── foreach($paths)
            │
            ├── fullPath = projectRoot + path
            ├── Vérifie isDirectory()
            └── scanner->scan(fullPath, maxDepth)
```

## Structure de recherche

### Ordre de priorité des chemins

1. **Config `directories`** (si `$config` fourni)
2. **Chemins par défaut** (`src/Directives`, `app/Directives`)
3. **Chemins personnalisés** (ajoutés via `addPath()`)

Les chemins sont fusionnés, pas remplacés.

### Chemins par défaut

```php
const DEFAULT_PATHS = [
    'src/Directives',    // 1. Pour les applications modernes
    'app/Directives',    // 2. Pour les applications Laravel traditionnelles
];
```

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Répertoire inexistant | Ignoré silencieusement | - |
| Répertoire non accessible | Ignoré silencieusement | - |
| Chemin invalide | Ignoré silencieusement | - |
| Project root non déterminable | Exception | `Unable to determine current working directory` |
| Erreur de scan | Ignorée (logique interne du scanner) | - |

### Exceptions explicites

| Exception | Condition | Message |
|-----------|-----------|---------|
| `\RuntimeException` | `getcwd()` retourne `false` | `Unable to determine current working directory` |

## Intégration

La classe `WorkspaceDirectiveDiscovery` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `FileSystemInterface` | Opérations sur le système de fichiers |
| `DirectiveScannerInterface` | Scan des classes PHP |
| `DirectiveConfigInterface` | Configuration optionnelle |
| `DirectiveDiscoveryService` | Orchestration de la découverte |

### Ordre dans le processus de découverte

```
1. BuiltInDirectiveDiscovery      (prioritaire)
2. WorkspaceDirectiveDiscovery    (projet)  ← Vous êtes ici
3. VendorDirectiveDiscovery        (packages)
4. CustomSources                  (personnalisées)
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(n) | n = nombre de dossiers à scanner |
| Cache | ✅ Oui | Les résultats sont mis en cache |
| Invalidation | Automatique | Le cache est vidé lors de l'ajout de chemins |
| Mémoire | ~1-2 MB | Dépend du nombre de fichiers PHP |

### Stratégie de cache

```php
public function discover(): array
{
    // Cache actif
    if ($this->cache !== null) {
        return $this->cache;
    }
    
    // Calcul et mise en cache
    $this->cache = $this->doDiscover();
    return $this->cache;
}

// Le cache est invalidé lors des modifications
public function addPath(string $path): self
{
    if (!in_array($path, $this->customPaths, true)) {
        $this->customPaths[] = $path;
        $this->cache = null; // Invalidation
    }
    return $this;
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| Laravel 9.x | ✅ Complet | - |
| Laravel 10.x | ✅ Complet | - |
| Laravel 11.x | ✅ Complet | - |
| Windows | ✅ Complet | Utilise `DIRECTORY_SEPARATOR` |
| Unix/Linux | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;
use PhpParser\ParserFactory;

// Créer les dépendances
$fileSystem = new FileSystemService();
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$scanner = new DirectiveClassScanner($fileSystem, $parser);

// Créer le discovery avec configuration par défaut
$discovery = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);

// Ajouter des chemins personnalisés
$discovery->addPaths([
    'app/CustomDirectives',
    'modules/Admin/Directives',
    'packages/Acme/Directives',
]);

// Découvrir les directives
$directives = $discovery->discover();

// Afficher les résultats
echo "Directives trouvées : " . count($directives) . PHP_EOL;

foreach ($directives as $fqcn) {
    echo "- " . $fqcn . PHP_EOL;
    
    // Analyser la directive
    $reflection = new ReflectionClass($fqcn);
    if (!$reflection->isAbstract()) {
        $instance = $reflection->newInstanceWithoutConstructor();
        echo "  Signature: " . $instance->getSignature() . PHP_EOL;
        
        $aliases = $instance->getAliases()->toArray();
        if (!empty($aliases)) {
            echo "  Aliases: " . implode(', ', $aliases) . PHP_EOL;
        }
    }
}

// Exemple avec configuration
$config = app(DirectiveConfigInterface::class);
$discoveryWithConfig = new WorkspaceDirectiveDiscovery(
    $fileSystem,
    $scanner,
    $config
);

// Les chemins de la config sont automatiquement utilisés
$discoveredDirectives = $discoveryWithConfig->discover();
```

## Bonnes pratiques

### 1. Organisation des directives

```
app/
├── Directives/                 # ✅ Bonne pratique
│   ├── UserDirective.php
│   └── AdminDirective.php
├── Console/
│   └── Directives/            # ✅ Alternative
├── Modules/
│   └── Admin/
│       └── Directives/        # ✅ Organisation modulaire
└── Directives/                 # ❌ Dossier à la racine (déconseillé)
```

### 2. Nommage des chemins

```php
// ✅ Utiliser des chemins relatifs à la racine
$discovery->addPath('app/Directives');

// ✅ Utiliser des chemins avec séparateur Unix
$discovery->addPath('app/Modules/Admin/Directives');

// ❌ Éviter les chemins absolus
$discovery->addPath('/var/www/project/app/Directives');

// ❌ Éviter les chemins avec ".."
$discovery->addPath('../app/Directives');
```

### 3. Gestion des modules

```php
class ModuleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $discovery = $this->app->make(WorkspaceDirectiveDiscovery::class);
        
        // Ajouter automatiquement les directives des modules
        foreach ($this->getActiveModules() as $module) {
            $discovery->addPath("modules/{$module}/Directives");
        }
    }
    
    private function getActiveModules(): array
    {
        // Logique pour déterminer les modules actifs
        return ['Admin', 'Api', 'Blog'];
    }
}
```
---