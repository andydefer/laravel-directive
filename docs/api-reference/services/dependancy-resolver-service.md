# DependencyResolverService - Référence Technique

## Description

Service de résolution récursive des dépendances Composer. Analyse l'arborescence complète des dépendances d'un projet à partir du fichier `composer.json` avec détection des cycles et limitation de profondeur.

## Hiérarchie / Implémentations

```
DependencyResolverInterface
    └── DependencyResolverService
```

## Rôle principal

`DependencyResolverService` parcourt récursivement toutes les dépendances d'un projet en lisant les fichiers `composer.json` de chaque package installé dans le dossier `vendor`. Il permet de :

- Résoudre l'intégralité de l'arbre des dépendances avec limite de profondeur
- Détecter les dépendances circulaires
- Obtenir une vue plate ou arborescente des dépendances
- Gérer les packages PHP méta (ignorés automatiquement)

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `ComposerReaderInterface` - Lecture du `composer.json` racine
- `FileSystemInterface` - Opérations sur le système de fichiers
- `StringTypedCollection` - Collection typée de chaînes
- PHP 8.1+

## API / Méthodes publiques

### `__construct(ComposerReaderInterface $composerReader, FileSystemInterface $fileSystem, int $maxDepth = 3)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$composerReader` | `ComposerReaderInterface` | Service de lecture du composer.json racine |
| `$fileSystem` | `FileSystemInterface` | Service de système de fichiers |
| `$maxDepth` | `int` | Profondeur maximale de résolution (défaut: 3) |

**Retourne :** `void`

**Exemple :**
```php
$reader = new ComposerReaderService($config, $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem, 5);
```

---

### `resolveAll(): array`

Résout toutes les dépendances du projet de manière récursive.

**Retourne :** `array<string, array<string, mixed>>` - Tableau associatif [nom du package => données du composer.json]

**Exceptions :** Aucune (les erreurs de lecture sont ignorées silencieusement)

**Exemple :**
```php
$dependencies = $resolver->resolveAll();
// [
//     'andydefer/domain-structures' => [
//         'name' => 'andydefer/domain-structures',
//         'version' => '1.21.1',
//         'require' => ['andydefer/php-vo' => '^0.13'],
//     ],
//     'andydefer/php-vo' => [...],
//     'laravel/framework' => [...],
// ]
```

---

### `resolvePackageDependencies(string $package): array`

Résout les dépendances d'un package spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$package` | `string` | Nom complet du package (ex: `andydefer/domain-structures`) |

**Retourne :** `array<string, array<string, mixed>>` - Dépendances du package

**Exceptions :** Aucune

**Exemple :**
```php
$deps = $resolver->resolvePackageDependencies('andydefer/domain-structures');
// ['andydefer/php-vo' => [...], 'andydefer/php-utils' => [...]]
```

---

### `getDependencyTree(): array`

Retourne l'arbre complet des dépendances sous forme hiérarchique.

**Retourne :** `array<string, array<string, mixed>>` - Arbre des dépendances

**Exceptions :** Aucune

**Exemple :**
```php
$tree = $resolver->getDependencyTree();
// [
//     'andydefer/domain-structures' => [
//         'andydefer/php-vo' => [
//             'andydefer/php-utils' => []
//         ]
//     ],
//     'laravel/framework' => [
//         'illuminate/contracts' => [],
//         'illuminate/support' => []
//     ]
// ]
```

---

### `getFlatDependencies(): StringTypedCollection`

Retourne une collection plate de tous les noms de packages.

**Retourne :** `StringTypedCollection` - Collection des noms de packages

**Exceptions :** Aucune

**Exemple :**
```php
$packages = $resolver->getFlatDependencies();
// StringTypedCollection ['andydefer/domain-structures', 'andydefer/php-vo', ...]

foreach ($packages as $package) {
    echo "- $package\n";
}
```

---

### `hasCircularDependency(): bool`

Vérifie la présence de dépendances circulaires dans l'arbre.

**Retourne :** `bool` - `true` si une dépendance circulaire est détectée

**Exceptions :** Aucune

**Exemple :**
```php
if ($resolver->hasCircularDependency()) {
    echo "Circular dependency detected!";
    // Le package A dépend de B qui dépend de C qui dépend de A
}
```

---

## Cas d'utilisation

### Cas 1 : Analyse complète des dépendances

```php
<?php

use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Config\Repository as ConfigRepository;

$configRepo = new ConfigRepository([
    'directive' => [
        'base_path' => __DIR__,
        'vendor_dir' => __DIR__ . '/vendor',
        'composer_path' => __DIR__ . '/composer.json',
    ]
]);

$config = new DirectiveConfig($configRepo);
$fileSystem = new FileSystemService();
$reader = new ComposerReaderService($config, $fileSystem);

// Résolution avec profondeur maximale de 4
$resolver = new DependencyResolverService($reader, $fileSystem, 4);

$allDependencies = $resolver->resolveAll();

echo "=== All Dependencies ===\n";
echo "Total: " . count($allDependencies) . " packages\n\n";

foreach ($allDependencies as $package => $data) {
    $requires = array_keys($data['require'] ?? []);
    echo "Package: $package\n";
    echo "Version: " . ($data['version'] ?? 'unknown') . "\n";
    echo "Requires: " . implode(', ', $requires) . "\n";
    echo "---\n";
}
```

### Cas 2 : Détection des dépendances circulaires

```php
<?php

$resolver = new DependencyResolverService($reader, $fileSystem);

if ($resolver->hasCircularDependency()) {
    // Récupérer l'arbre pour identifier le cycle
    $tree = $resolver->getDependencyTree();
    
    // Analyser l'arbre pour trouver le cycle
    function findCycle(array $tree, array $path = []): ?array
    {
        foreach ($tree as $package => $children) {
            if (in_array($package, $path, true)) {
                return array_merge($path, [$package]);
            }
            
            $cycle = findCycle($children, array_merge($path, [$package]));
            if ($cycle !== null) {
                return $cycle;
            }
        }
        
        return null;
    }
    
    $cycle = findCycle($tree);
    if ($cycle) {
        echo "Cycle detected: " . implode(' → ', $cycle) . "\n";
        // Exemple: "package-a → package-b → package-c → package-a"
    }
}
```

### Cas 3 : Génération d'un rapport de dépendances

```php
<?php

$resolver = new DependencyResolverService($reader, $fileSystem);

// 1. Collection plate
$packages = $resolver->getFlatDependencies();

echo "=== Dependency Report ===\n";
echo "Total packages: " . $packages->count() . "\n\n";

// 2. Compter les dépendances par vendor
$vendorCounts = [];
foreach ($packages as $package) {
    $vendor = explode('/', $package)[0];
    $vendorCounts[$vendor] = ($vendorCounts[$vendor] ?? 0) + 1;
}

echo "Vendor statistics:\n";
foreach ($vendorCounts as $vendor => $count) {
    echo "  - $vendor: $count package(s)\n";
}

// 3. Vérifier un package spécifique
if ($packages->contains('andydefer/domain-structures')) {
    echo "\n✅ domain-structures is present in the project\n";
    $deps = $resolver->resolvePackageDependencies('andydefer/domain-structures');
    echo "It depends on " . count($deps) . " package(s)\n";
}
```

### Cas 4 : Visualisation de l'arbre des dépendances

```php
<?php

$tree = $resolver->getDependencyTree();

function printTree(array $tree, int $level = 0): void
{
    $indent = str_repeat('  ', $level);
    
    foreach ($tree as $package => $children) {
        echo $indent . "- $package\n";
        
        if (isset($children['__truncated__']) && $children['__truncated__'] === true) {
            echo $indent . "  ... (truncated at max depth)\n";
            continue;
        }
        
        if (!empty($children)) {
            printTree($children, $level + 1);
        }
    }
}

echo "=== Dependency Tree ===\n";
printTree($tree);
```

### Cas 5 : Profondeur maximale personnalisée

```php
<?php

// Profondeur 1 : seulement les dépendances directes
$shallow = new DependencyResolverService($reader, $fileSystem, 1);
$directDeps = $shallow->resolveAll();
echo "Direct dependencies: " . count($directDeps) . "\n";

// Profondeur 5 : analyse approfondie (défaut est 3)
$deep = new DependencyResolverService($reader, $fileSystem, 5);
$allDeps = $deep->resolveAll();
echo "Deep dependencies: " . count($allDeps) . "\n";
```

---

## Flux d'exécution

```
resolveAll()
    ↓
resetState() → vider resolved + visited
    ↓
getRequire() (via ComposerReader)
    ↓
Pour chaque package racine
    ├── Si package PHP → ignorer
    └── resolvePackage($package, depth=0)
        ├── shouldSkipResolution()?
        │   ├── depth > maxDepth → ignorer
        │   └── déjà visité → ignorer
        ├── Marquer comme visité
        ├── loadComposerData($package)
        │   ├── vendor/{package}/composer.json
        │   ├── Fichier existe ?
        │   ├── Lecture du fichier
        │   └── Décodage JSON
        ├── Stocker les données
        └── Pour chaque dépendance
            └── resolvePackage($dependency, depth+1)
    ↓
Retourner $resolved (tous les packages)
```

### Détection des cycles

```
hasCircularDependency()
    ↓
Pour chaque package racine
    ├── resetState()
    └── detectCycle($package, [], 0)
        ├── depth > maxDepth → false
        ├── package déjà dans $path → cycle détecté
        ├── Ajouter package au $path
        ├── loadComposerData($package)
        ├── Pour chaque dépendance
        │   └── detectCycle($dependency, $path, depth+1)
        └── false (aucun cycle)
    ↓
true si cycle détecté
```

---

## Gestion des erreurs

Aucune exception n'est levée. Les erreurs sont gérées silencieusement :

| Situation | Comportement |
|-----------|--------------|
| Package non trouvé | Ignoré (continuation) |
| composer.json manquant | Ignoré (continuation) |
| Fichier non lisible | Ignoré (continuation) |
| JSON invalide | Ignoré (continuation) |
| Dépendance circulaire | Détectée via `hasCircularDependency()` |
| Profondeur maximale atteinte | Truncation avec `__truncated__` |

---

## Intégration

### Avec ComposerReaderService

```php
$reader = new ComposerReaderService($config, $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem);
```

### Dans un framework Laravel

```php
// ServiceProvider
$this->app->singleton(DependencyResolverInterface::class, function ($app) {
    return new DependencyResolverService(
        $app->make(ComposerReaderInterface::class),
        $app->make(FileSystemInterface::class),
        config('directive.max_depth', 3) // Profondeur configurable
    );
});
```

### Utilisation dans une commande

```php
<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DependencyCommand extends Command
{
    private DependencyResolverService $resolver;
    
    public function __construct(DependencyResolverService $resolver)
    {
        parent::__construct();
        $this->resolver = $resolver;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packages = $this->resolver->getFlatDependencies();
        $output->writeln('Total packages: ' . $packages->count());
        
        if ($this->resolver->hasCircularDependency()) {
            $output->writeln('<error>Circular dependency detected!</error>');
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `resolveAll()` | O(n × d) | n = nombre de packages, d = profondeur |
| `resolvePackageDependencies()` | O(n × d) | Pour un package spécifique |
| `getDependencyTree()` | O(n × d) | Construction récursive de l'arbre |
| `getFlatDependencies()` | O(n) | Aplatissement des résultats |
| `hasCircularDependency()` | O(n × d) | Détection de cycles |

**Optimisations :**
- Visite unique des packages (évite les boucles infinies)
- Pas de relecture des fichiers déjà visités
- Les dépendances sont mises en cache dans `$resolved`
- Limitation de profondeur (`maxDepth`) pour éviter les explosions combinatoires

**Mémoire :**
- Toutes les dépendances résolues sont stockées dans `$resolved`
- `$visited` suit les packages déjà parcourus
- L'arbre des dépendances peut être volumineux (mise en garde pour les grands projets)

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
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Config\Repository as ConfigRepository;

// 1. Configuration
$configRepo = new ConfigRepository([
    'directive' => [
        'base_path' => '/var/www/myapp',
        'vendor_dir' => '/var/www/myapp/vendor',
        'composer_path' => '/var/www/myapp/composer.json',
    ]
]);

$config = new DirectiveConfig($configRepo);
$fileSystem = new FileSystemService();
$reader = new ComposerReaderService($config, $fileSystem);

// 2. Création du résolveur avec profondeur 4
$resolver = new DependencyResolverService($reader, $fileSystem, 4);

try {
    // 3. Résoudre toutes les dépendances
    $allDependencies = $resolver->resolveAll();
    echo "=== All Dependencies ===\n";
    echo "Total: " . count($allDependencies) . " packages\n\n";

    // 4. Afficher les dépendances directes
    $directDependencies = $reader->getRequire();
    echo "=== Direct Dependencies ===\n";
    foreach ($directDependencies as $package => $version) {
        $deps = $resolver->resolvePackageDependencies($package);
        echo "$package ($version) depends on " . count($deps) . " package(s)\n";
    }

    // 5. Obtenir l'arbre
    $tree = $resolver->getDependencyTree();
    echo "\n=== Dependency Tree (first 2 levels) ===\n";
    $level = 0;
    foreach ($tree as $package => $children) {
        if ($level >= 2) break;
        echo "- $package\n";
        foreach ($children as $child => $grandChildren) {
            if (!is_array($grandChildren)) continue;
            echo "  - $child\n";
        }
        echo "\n";
        $level++;
    }

    // 6. Collection plate
    $flatPackages = $resolver->getFlatDependencies();
    echo "\n=== Top 5 Packages ===\n";
    $count = 0;
    foreach ($flatPackages as $package) {
        if ($count >= 5) break;
        echo "- $package\n";
        $count++;
    }

    // 7. Vérifier les cycles
    if ($resolver->hasCircularDependency()) {
        echo "\n⚠️ Circular dependency detected!\n";
    } else {
        echo "\n✅ No circular dependencies found\n";
    }

    // 8. Vérifier un package spécifique
    $package = 'andydefer/domain-structures';
    if ($flatPackages->contains($package)) {
        echo "\n✅ $package is installed\n";
        $deps = $resolver->resolvePackageDependencies($package);
        echo "It depends on:\n";
        foreach ($deps as $dep => $data) {
            echo "  - $dep\n";
        }
    }

} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
```

## Voir aussi

- `ComposerReaderService` - Lecture du composer.json
- `ComposerReaderInterface` - Interface du lecteur
- `FileSystemService` - Service de système de fichiers
- `StringTypedCollection` - Collection typée de chaînes