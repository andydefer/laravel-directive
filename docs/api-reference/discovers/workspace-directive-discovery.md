# WorkspaceDirectiveDiscovery - Référence Technique

## Description

Source de découverte des directives dans l'espace de travail de l'application. Scanne les répertoires propres à l'application pour trouver les classes de directives définies par l'utilisateur.

## Hiérarchie / Implémentations

```
DiscoverySourceInterface
    └── WorkspaceDirectiveDiscovery
```

## Rôle principal

`WorkspaceDirectiveDiscovery` est la source de découverte pour les directives de l'application. Elle permet de :

- Scanner les répertoires par défaut (`src/Directives`, `app/Directives`)
- Ajouter des chemins personnalisés via configuration ou programmatiquement
- Lire les sources personnalisées depuis la configuration du package
- Mettre en cache les résultats pour optimiser les performances
- Scanner l'arborescence avec une profondeur configurable

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `FileSystemInterface` - Opérations sur le système de fichiers
- `DirectiveScannerInterface` - Scanner des classes de directives
- `DirectiveConfigInterface` - Configuration du package (optionnel)
- PHP 8.1+

## API / Méthodes publiques

### `__construct(FileSystemInterface $fileSystem, DirectiveScannerInterface $scanner, ?DirectiveConfigInterface $config = null, int $maxDepth = 3)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fileSystem` | `FileSystemInterface` | Service de système de fichiers |
| `$scanner` | `DirectiveScannerInterface` | Scanner de directives |
| `$config` | `?DirectiveConfigInterface` | Configuration (optionnel) |
| `$maxDepth` | `int` | Profondeur de scan (défaut: 3) |

**Retourne :** `void`

**Exemple :**
```php
$workspaceDiscovery = new WorkspaceDirectiveDiscovery(
    $fileSystem,
    $scanner,
    $config,
    4
);
```

---

### `discover(): array`

Découvre les directives dans l'espace de travail.

**Retourne :** `array<int, string>` - Liste des FQCN des directives trouvées

**Exceptions :** `RuntimeException` - Si le répertoire courant ne peut être déterminé

**Exemple :**
```php
$directives = $workspaceDiscovery->discover();

foreach ($directives as $class) {
    echo "Directive trouvée: $class\n";
}
// App\Directives\GreetDirective
// App\Directives\HelpDirective
// App\Commands\CustomDirective
```

---

### `addPath(string $path): self`

Ajoute un chemin personnalisé à scanner.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Chemin relatif à la racine du projet |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$workspaceDiscovery
    ->addPath('src/Commands')
    ->addPath('lib/Directives');
```

---

### `addPaths(array $paths): self`

Ajoute plusieurs chemins personnalisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$paths` | `array<int, string>` | Chemins relatifs à la racine du projet |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$workspaceDiscovery->addPaths([
    'src/Console',
    'app/Directives',
    'packages/core/src/Directives',
]);
```

---

## Cas d'utilisation

### Cas 1 : Découverte standard

```php
<?php

use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;
use PhpParser\ParserFactory;

$fileSystem = new FileSystemService();
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$scanner = new DirectiveClassScanner($fileSystem, $parser);

$workspaceDiscovery = new WorkspaceDirectiveDiscovery(
    $fileSystem,
    $scanner
);

$directives = $workspaceDiscovery->discover();

echo "Directives trouvées: " . count($directives) . "\n";
// App\Directives\GreetDirective
// App\Directives\HelpDirective
```

### Cas 2 : Structure de répertoires typique

```php
<?php

// Structure du projet
// project/
// ├── src/
// │   └── Directives/
// │       ├── GreetDirective.php
// │       └── HelpDirective.php
// ├── app/
// │   └── Directives/
// │       ├── AdminDirective.php
// │       └── UserDirective.php
// ├── lib/
// │   └── Commands/
// │       └── CustomDirective.php
// └── config/
//     └── directive.php

// Par défaut: src/Directives et app/Directives sont scannés
// Résultat: GreetDirective, HelpDirective, AdminDirective, UserDirective
```

### Cas 3 : Ajout de chemins personnalisés

```php
<?php

$workspaceDiscovery = new WorkspaceDirectiveDiscovery(
    $fileSystem,
    $scanner,
    $config
);

// Ajouter des chemins spécifiques
$workspaceDiscovery
    ->addPath('lib/Commands')
    ->addPath('packages/admin/src/Directives')
    ->addPath('app/Console/Commands');

$directives = $workspaceDiscovery->discover();
// Les 3 nouveaux dossiers sont scannés en plus des dossiers par défaut
```

### Cas 4 : Configuration via directive.config

```php
<?php

// config/directive.php
<?php

return [
    'directories' => [
        'app/Commands',
        'src/Directives',
        'lib/Console',
    ],
    'custom_sources' => [
        'packages/core/src',
        'modules/admin/src/Directives',
    ],
];

// Le service utilisera les chemins configurés
$workspaceDiscovery = new WorkspaceDirectiveDiscovery(
    $fileSystem,
    $scanner,
    $config // DirectiveConfig avec les chemins définis
);

$directives = $workspaceDiscovery->discover();
```

### Cas 5 : Utilisation avec DirectiveDiscoveryService

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

$discoveryService = DirectiveDiscoveryService::init($container);

// Le service utilise WorkspaceDirectiveDiscovery automatiquement
// si la source WORKSPACE n'est pas ignorée
$directives = $discoveryService
    ->enableSource(DiscoverySource::WORKSPACE)
    ->addSource('src/Commands') // Ajoute un custom source
    ->discover();

echo "Directives workspace: " . $directives->count() . "\n";
```

---

## Flux d'exécution

```
discover()
    ↓
Vérifier le cache
    ├── Cache existe → retourner
    └── Cache vide → doDiscover()
    ↓
doDiscover()
    ↓
getProjectRoot() → getcwd()
    ↓
getScanPaths()
    ├── DEFAULT_PATHS (src/Directives, app/Directives)
    ├── Config->getDirectories() (si config fourni)
    └── customPaths (via addPath)
    ↓
Pour chaque chemin
    ├── fullPath = projectRoot/path
    ├── Si répertoire existe
    │   └── scanner->scan(fullPath, maxDepth)
    │       ├── src/Directives/GreetDirective.php
    │       ├── src/Directives/HelpDirective.php
    │       └── app/Directives/AdminDirective.php
    └── Ajouter les classes trouvées
    ↓
scanWorkspaceCustomSources()
    ├── Config->getCustomSources()
    ├── Pour chaque source
    │   ├── fullPath = projectRoot/source
    │   ├── Si répertoire existe
    │   │   └── scanner->scan(fullPath, maxDepth)
    │   └── Ajouter les classes
    └── Retourner les directives
    ↓
Mettre en cache les résultats
    ↓
Retourner le tableau des FQCN
```

---

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Répertoire inexistant | Ignoré (continuation) |
| Aucun répertoire trouvé | Retourne un tableau vide |
| Erreur de parsing PHP | Ignorée par le scanner |
| CWD impossible | `RuntimeException` |

**Exceptions :**
- `RuntimeException` - Si `getcwd()` échoue

---

## Intégration

### Avec DirectiveDiscoveryService

```php
// Dans DirectiveDiscoveryService
private function discoverWorkspaceDirectives(): void
{
    $source = new WorkspaceDirectiveDiscovery(
        $this->getFileSystem(),
        $this->getScanner(),
        $this->getConfig(),
        $this->maxDepth
    );
    
    $fqcns = $source->discover();
    
    foreach ($fqcns as $fqcn) {
        $this->addDirectiveFromFqcn($fqcn, false);
    }
}
```

### Avec Laravel

```php
<?php

namespace App\Providers;

use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use Illuminate\Support\ServiceProvider;

class DirectiveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $discovery = app(DirectiveDiscoveryService::class);
        
        // Ajouter des chemins Laravel spécifiques
        $workspaceDiscovery = app(WorkspaceDirectiveDiscovery::class);
        $workspaceDiscovery->addPaths([
            app_path('Commands'),
            app_path('Directives'),
            app_path('Console/Commands'),
        ]);
        
        // Désactiver la découverte workspace si nécessaire
        $discovery->ignoreSource(DiscoverySource::WORKSPACE);
    }
}
```

### Extension personnalisée

```php
<?php

class ExtendedWorkspaceDiscovery extends WorkspaceDirectiveDiscovery
{
    private array $ignoredPaths = [];
    
    public function ignorePath(string $path): self
    {
        $this->ignoredPaths[] = $path;
        return $this;
    }
    
    private function doDiscover(): array
    {
        $directives = parent::doDiscover();
        
        // Filtrer les résultats
        return array_filter($directives, function($class) {
            foreach ($this->ignoredPaths as $ignored) {
                if (strpos($class, $ignored) !== false) {
                    return false;
                }
            }
            return true;
        });
    }
}
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `discover()` | O(1) | Lecture du cache (après première exécution) |
| `doDiscover()` | O(n × d) | n = fichiers, d = profondeur |
| `addPath()` | O(1) | Ajout dans un tableau |

**Optimisations :**
- Cache des résultats après la première découverte
- Ignorance des répertoires inexistants
- Profondeur configurable pour limiter l'exploration
- Pas de re-scan si les chemins ne changent pas

**Mémoire :**
- Les résultats sont mis en cache
- Les chemins sont stockés en mémoire
- Les FQCN sont conservés dans le cache

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

use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Config\Repository as ConfigRepository;
use PhpParser\ParserFactory;

// 1. Configuration
$configRepo = new ConfigRepository([
    'directive' => [
        'directories' => [
            'app/Commands',
            'src/Directives',
            'lib/Console',
        ],
        'custom_sources' => [
            'packages/admin/src',
            'modules/core/src/Directives',
        ],
    ]
]);

$config = new DirectiveConfig($configRepo);
$fileSystem = new FileSystemService();
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$scanner = new DirectiveClassScanner($fileSystem, $parser);

// 2. Création du discovery service
$workspaceDiscovery = new WorkspaceDirectiveDiscovery(
    $fileSystem,
    $scanner,
    $config,
    4 // Profondeur
);

// 3. Ajout de chemins personnalisés
$workspaceDiscovery->addPaths([
    'src/Console/Commands',
    'app/Console',
    'lib/Directives',
]);

// 4. Découverte
echo "=== Découverte des directives workspace ===\n\n";
$directives = $workspaceDiscovery->discover();

echo "Total directives trouvées: " . count($directives) . "\n\n";

// 5. Analyse par namespace
$namespaceMap = [];
foreach ($directives as $class) {
    $parts = explode('\\', $class);
    if (count($parts) >= 2) {
        $namespace = $parts[0] . '\\' . $parts[1];
        if (!isset($namespaceMap[$namespace])) {
            $namespaceMap[$namespace] = [];
        }
        $namespaceMap[$namespace][] = $class;
    }
}

echo "=== Répartition par namespace ===\n";
foreach ($namespaceMap as $namespace => $classes) {
    echo "$namespace: " . count($classes) . " directive(s)\n";
    foreach ($classes as $class) {
        echo "  - $class\n";
    }
    echo "\n";
}

// 6. Chemins scannés
echo "=== Chemins scannés ===\n";

// Chemins par défaut + configuration + personnalisés
$paths = array_merge(
    ['src/Directives', 'app/Directives'],
    $config->getDirectories(),
    $workspaceDiscovery->customPaths // Note: propriété privée, utilisez getter si disponible
);

$uniquePaths = array_unique($paths);
foreach ($uniquePaths as $path) {
    $fullPath = getcwd() . '/' . $path;
    $exists = is_dir($fullPath) ? '✅' : '❌';
    echo "$exists $path\n";
}
echo "\n";

// 7. Sources personnalisées
echo "=== Sources personnalisées ===\n";
$customSources = $config->getCustomSources();
foreach ($customSources as $source) {
    $fullPath = getcwd() . '/' . $source;
    $exists = is_dir($fullPath) ? '✅' : '❌';
    echo "$exists $source\n";
}
echo "\n";

// 8. Statistiques
echo "=== Statistiques ===\n";
echo "Nombre de chemins configurés: " . count(array_unique($paths)) . "\n";
echo "Nombre de sources personnalisées: " . count($customSources) . "\n";
echo "Nombre de directives trouvées: " . count($directives) . "\n";

// 9. Vérification des classes
echo "\n=== Vérification des classes ===\n";
if (count($directives) > 0) {
    // Vérifier que les classes existent
    $valid = 0;
    $invalid = 0;
    
    foreach ($directives as $class) {
        if (class_exists($class)) {
            $valid++;
        } else {
            $invalid++;
            echo "⚠️ Classe non trouvée: $class\n";
        }
    }
    
    echo "\nClasses valides: $valid\n";
    echo "Classes invalides: $invalid\n";
}

// 10. Test de cache
echo "\n=== Test du cache ===\n";
$startTime = microtime(true);
$firstResult = $workspaceDiscovery->discover();
$firstTime = microtime(true) - $startTime;

$startTime = microtime(true);
$secondResult = $workspaceDiscovery->discover();
$secondTime = microtime(true) - $startTime;

echo "Première découverte: " . round($firstTime * 1000, 2) . " ms\n";
echo "Deuxième découverte (cache): " . round($secondTime * 1000, 2) . " ms\n";
echo "Gain de performance: " . round(($firstTime / $secondTime), 2) . "x\n";
echo "Même résultat: " . ($firstResult === $secondResult ? '✅' : '❌') . "\n";
```

## Voir aussi

- `DiscoverySourceInterface` - Interface de source de découverte
- `DirectiveClassScanner` - Scanner des classes
- `DirectiveConfigInterface` - Configuration du package
- `VendorDirectiveDiscovery` - Découverte dans les vendors
- `BuiltInDirectiveDiscovery` - Découverte des directives intégrées