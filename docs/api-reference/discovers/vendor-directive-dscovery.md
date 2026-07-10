# VendorDirectiveDiscovery - Référence Technique

## Description

Source de découverte des directives dans les packages vendors. Scanne les packages Composer installés pour trouver les classes de directives via les chemins PSR-4 et les fichiers de configuration personnalisés.

## Hiérarchie / Implémentations

```
DiscoverySourceInterface
    └── AbstractDiscovery
        └── VendorDirectiveDiscovery
```

## Rôle principal

`VendorDirectiveDiscovery` est la source de découverte pour les packages tiers. Elle permet de :

- Scanner tous les packages installés via Composer
- Explorer les chemins PSR-4 à la recherche d'un sous-dossier `Directives`
- Lire les fichiers `config/directive.php` des packages pour des sources personnalisées
- Résoudre les dépendances récursivement
- Analyser les packages avec une profondeur de scan configurable
- Hériter des fonctionnalités de suivi des problèmes via `AbstractDiscovery`

## Installation

```bash
composer require andydefer/laravel-directive
```

### Dépendances

- `ComposerReaderInterface` - Lecture de composer.json
- `DependencyResolverInterface` - Résolution des dépendances
- `FileSystemInterface` - Opérations sur le système de fichiers
- `DirectiveScannerInterface` - Scan des classes de directives
- `AbstractDiscovery` - Classe de base avec gestion des problèmes
- PHP 8.1+

## API / Méthodes publiques

### `__construct(ComposerReaderInterface $composerReader, DependencyResolverInterface $dependencyResolver, FileSystemInterface $fileSystem, DirectiveScannerInterface $scanner, int $maxDepth = 3)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$composerReader` | `ComposerReaderInterface` | Service de lecture composer.json |
| `$dependencyResolver` | `DependencyResolverInterface` | Résolveur de dépendances |
| `$fileSystem` | `FileSystemInterface` | Service de système de fichiers |
| `$scanner` | `DirectiveScannerInterface` | Scanner de directives |
| `$maxDepth` | `int` | Profondeur de scan (défaut: 3) |

**Retourne :** `void`

**Exemple :**
```php
$vendorDiscovery = new VendorDirectiveDiscovery(
    $composerReader,
    $dependencyResolver,
    $fileSystem,
    $scanner,
    4 // Profondeur de scan
);
```

---

### `discover(): array`

Découvre les directives dans tous les packages vendors.

**Retourne :** `array<int, string>` - Liste des FQCN des directives trouvées

**Exceptions :** Aucune (les erreurs sont silencieusement ignorées et ajoutées comme problèmes)

**Exemple :**
```php
$directives = $vendorDiscovery->discover();

echo "Directives trouvées dans les vendors:\n";
foreach ($directives as $class) {
    echo "- $class\n";
}
// AndyDefer\Directive\BuiltIn\HelpDirective
// Vendor\Package\Directives\CustomDirective
// AnotherVendor\Package\Directives\AnotherDirective
```

---

### `getProblems(): ListCollection` (hérité de AbstractDiscovery)

Retourne la collection des problèmes rencontrés lors de la découverte.

**Retourne :** `ListCollection` - Collection des problèmes

**Exemple :**
```php
$vendorDiscovery = new VendorDirectiveDiscovery(...);
$directives = $vendorDiscovery->discover();
$problems = $vendorDiscovery->getProblems();

if ($problems->isNotEmpty()) {
    foreach ($problems as $problem) {
        echo $problem->get('message') . "\n";
    }
}
```

---

### `clearProblems(): self` (hérité de AbstractDiscovery)

Efface tous les problèmes.

**Retourne :** `self` - Instance fluide

---

## Cas d'utilisation

### Cas 1 : Découverte standard des packages vendors

```php
<?php

use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;
use PhpParser\ParserFactory;

$fileSystem = new FileSystemService();
$config = new DirectiveConfig(...);
$composerReader = new ComposerReaderService($config, $fileSystem);
$dependencyResolver = new DependencyResolverService($composerReader, $fileSystem);

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$scanner = new DirectiveClassScanner($fileSystem, $parser);

$vendorDiscovery = new VendorDirectiveDiscovery(
    $composerReader,
    $dependencyResolver,
    $fileSystem,
    $scanner
);

$directives = $vendorDiscovery->discover();

echo "Total directives vendors: " . count($directives) . "\n";
foreach ($directives as $class) {
    echo "- $class\n";
}
```

### Cas 2 : Structure PSR-4 typique d'un package

```php
<?php

// Structure d'un package vendor typique
// vendor/andydefer/directive/
// ├── composer.json
// ├── src/
// │   ├── BuiltIn/
// │   │   ├── HelpDirective.php
// │   │   ├── ListDirective.php
// │   │   └── VersionDirective.php
// │   └── Directives/
// │       └── CustomDirective.php
// └── config/
//     └── directive.php

// Le scanner découvre:
// - vendor/andydefer/directive/src/BuiltIn/ListDirective
// - vendor/andydefer/directive/src/BuiltIn/HelpDirective
// - vendor/andydefer/directive/src/Directives/CustomDirective
```

### Cas 3 : Package avec configuration personnalisée

```php
<?php

// config/directive.php dans un package vendor
<?php

return [
    'custom_sources' => [
        'src/Commands',
        'src/Console',
        'lib/Directives',
    ],
];

// Le scanner explorera ces dossiers supplémentaires
// vendor/package/src/Commands/
// vendor/package/src/Console/
// vendor/package/lib/Directives/
```

### Cas 4 : Filtrage des packages PHP méta

```php
<?php

$composerData = [
    'require' => [
        'php' => '^8.1',           // Ignoré
        'ext-json' => '*',         // Ignoré
        'andydefer/directive' => '^1.0', // Scanné
        'vendor/package' => '^2.0', // Scanné
    ]
];

// Les packages 'php' et 'ext-*' sont automatiquement ignorés
```

### Cas 5 : Intégration dans DirectiveDiscoveryService

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

$discoveryService = DirectiveDiscoveryService::init($container);

// Le service utilise VendorDirectiveDiscovery automatiquement
// si la source VENDOR n'est pas ignorée
$directives = $discoveryService
    ->enableSource(DiscoverySource::VENDOR)
    ->discover();

echo "Total directives (incluant vendors): " . $directives->count() . "\n";
```

### Cas 6 : Suivi des problèmes de découverte

```php
<?php

$vendorDiscovery = new VendorDirectiveDiscovery(...);
$directives = $vendorDiscovery->discover();

$problems = $vendorDiscovery->getProblems();

if ($problems->isNotEmpty()) {
    echo "Problèmes rencontrés:\n";
    foreach ($problems as $problem) {
        $key = $problem->get('key');
        $context = $problem->get('context');
        $message = $problem->get('message');
        echo "  ❌ [$key] $context\n";
        echo "     $message\n";
    }
}
```

---

## Flux d'exécution

```
discover()
    ↓
getFlatDependencies() (via DependencyResolver)
    ├── Résoudre tous les packages
    └── Retourner la liste plate
    ↓
Pour chaque package
    ↓
scanPackage($package)
    ├── getPackagePath($package)
    │   └── vendor_dir/{package}
    ├── scanAutoloadPaths($package, $packagePath)
    │   ├── readComposerJson()
    │   │   └── packagePath/composer.json
    │   ├── Extraire autoload.psr-4
    │   ├── Pour chaque namespace => path
    │   │   └── path/Directives/
    │   └── scanner->scan($fullPath, $maxDepth)
    ├── scanCustomSources($package, $packagePath)
    │   ├── configPath = packagePath/config/directive.php
    │   ├── Si existe → require()
    │   ├── Extraire custom_sources
    │   └── scanner->scan($fullPath, $maxDepth)
    └── Retourner les directives trouvées
    ↓
Fusionner tous les résultats
    ↓
Retourner le tableau des FQCN
```

### Scan PSR-4 détaillé

```
scanAutoloadPaths($package, $packagePath)
    ↓
readComposerJson($packagePath)
    ↓
Extraire 'autoload' => ['psr-4' => [...]]
    ↓
Pour chaque mapping namespace => path
    ├── namespace: 'AndyDefer\Directive\\'
    ├── path: 'src/'
    ├── fullPath = packagePath/src/Directives
    ├── Si répertoire existe
    │   └── scanner->scan(fullPath, maxDepth)
    │       ├── src/Directives/HelpDirective.php
    │       ├── src/Directives/ListDirective.php
    │       └── src/Directives/VersionDirective.php
    └── Ajouter les classes trouvées
```

### Gestion des problèmes

```
En cas d'erreur à chaque étape
    ↓
addProblem()
    ├── key: identifiant unique
    ├── context: description du contexte
    ├── message: message d'erreur
    └── context_data: données additionnelles
    ↓
Continuer l'exécution (ne pas bloquer)
    ↓
Les problèmes sont collectés et accessibles via getProblems()
```

---

## Gestion des erreurs

| Situation | Clé du problème | Contexte | Données |
|-----------|-----------------|----------|---------|
| Échec de résolution des packages | `resolve_packages` | `Failed to resolve vendor packages` | - |
| Échec de scan d'un package | `scan_package` | `Failed to scan package: {package}` | `package` |
| Échec de scan d'un package (général) | `scan_package_error` | `Failed to scan package: {package}` | `package` |
| Échec de scan des chemins autoload | `scan_autoload_paths` | `Failed to scan autoload paths for package: {package}` | `package` |
| Échec de scan d'un chemin PSR-4 | `scan_autoload_path` | `Failed to scan autoload path: {path}` | `package`, `path`, `namespace` |
| Échec de scan des sources personnalisées | `scan_custom_sources` | `Failed to scan custom sources for package: {package}` | `package` |
| Échec de scan d'une source personnalisée | `scan_custom_source` | `Failed to scan custom source: {path}` | `package`, `source`, `full_path` |
| Source personnalisée non répertoire | `custom_source_not_directory` | `Custom source path is not a directory: {path}` | `package`, `source`, `full_path` |
| Échec de lecture de composer.json | `read_composer_json` | `Failed to read composer.json for package: {package}` | `composer_path` |
| Échec d'extraction des sources custom | `extract_custom_sources` | `Failed to extract custom sources from config: {path}` | `config_path` |

**Aucune exception n'est levée.** Les erreurs sont capturées et ajoutées comme problèmes.

---

## Intégration

### Avec DirectiveDiscoveryService

```php
// Dans DirectiveDiscoveryService
private function discoverVendorDirectives(): void
{
    $config = $this->getConfig();
    $fileSystem = $this->getFileSystem();
    
    $composerReader = new ComposerReaderService($config, $fileSystem);
    $dependencyResolver = new DependencyResolverService($composerReader, $fileSystem);
    
    $source = new VendorDirectiveDiscovery(
        $composerReader,
        $dependencyResolver,
        $fileSystem,
        $this->getScanner(),
        $this->maxDepth
    );
    
    $fqcns = $source->discover();
    
    // Récupérer les problèmes de la source
    foreach ($source->getProblems() as $problem) {
        $this->addProblem(
            'vendor_' . $problem->get('key'),
            $problem->get('context'),
            $problem->get('message'),
            $problem->get('context_data')->toArray()
        );
    }
    
    foreach ($fqcns as $fqcn) {
        $this->addDirectiveFromFqcn($fqcn, false);
    }
}
```

### Dans un framework Laravel

```php
<?php

namespace App\Providers;

use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use Illuminate\Support\ServiceProvider;

class DirectiveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $discovery = app(DirectiveDiscoveryService::class);
        
        // Désactiver la découverte des vendors si nécessaire
        $discovery->ignoreSource(DiscoverySource::VENDOR);
        
        // Ou configurer la profondeur
        $vendorDiscovery = app(VendorDirectiveDiscovery::class);
        $vendorDiscovery->setMaxDepth(5);
    }
}
```

### Extension personnalisée

```php
<?php

class ExtendedVendorDiscovery extends VendorDirectiveDiscovery
{
    private array $extraDirectories = [];
    
    public function addVendorPath(string $vendor, string $path): self
    {
        $this->extraDirectories[$vendor] = $path;
        return $this;
    }
    
    private function scanPackage(string $package): array
    {
        // Scan standard
        $directives = parent::scanPackage($package);
        
        // Ajouter des chemins personnalisés
        if (isset($this->extraDirectories[$package])) {
            $customPath = $this->getPackagePath($package) . '/' . $this->extraDirectories[$package];
            if ($this->fileSystem->isDirectory($customPath)) {
                $extra = $this->scanner->scan($customPath, $this->maxDepth);
                $directives = array_merge($directives, $extra);
            }
        }
        
        return $directives;
    }
}
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `discover()` | O(n × p × d) | n = packages, p = PSR-4 paths, d = profondeur |
| `scanPackage()` | O(p × d) | p = PSR-4 paths, d = profondeur |
| `scanAutoloadPaths()` | O(p × d) | p = PSR-4 paths, d = profondeur |
| `scanCustomSources()` | O(c × d) | c = custom sources, d = profondeur |

**Optimisations :**
- Utilisation de `getFlatDependencies()` pour une liste plate
- Scan limité aux chemins PSR-4
- Ignorance des erreurs pour ne pas bloquer le processus
- Profondeur configurable pour limiter l'exploration

**Mémoire :**
- Toutes les directives sont stockées dans un tableau
- Les packages sont traités un par un
- Les FQCN sont conservés en mémoire

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
use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Config\Repository as ConfigRepository;
use PhpParser\ParserFactory;

// 1. Configuration
$configRepo = new ConfigRepository([
    'directive' => [
        'base_path' => __DIR__,
        'vendor_dir' => __DIR__ . '/vendor',
        'composer_path' => __DIR__ . '/composer.json',
    ]
]);

$config = new DirectiveConfig($configRepo);
$fileSystem = new FileSystemService();
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$scanner = new DirectiveClassScanner($fileSystem, $parser);

// 2. Services Composer
$composerReader = new ComposerReaderService($config, $fileSystem);
$dependencyResolver = new DependencyResolverService($composerReader, $fileSystem);

// 3. Découverte des vendors
$vendorDiscovery = new VendorDirectiveDiscovery(
    $composerReader,
    $dependencyResolver,
    $fileSystem,
    $scanner,
    3 // Profondeur
);

echo "=== Découverte des directives dans les vendors ===\n\n";

// 4. Découverte
$directives = $vendorDiscovery->discover();

echo "Total directives trouvées: " . count($directives) . "\n\n";

// 5. Analyse par vendor
$vendorMap = [];
foreach ($directives as $class) {
    $parts = explode('\\', $class);
    $vendor = $parts[0] ?? 'unknown';
    if (!isset($vendorMap[$vendor])) {
        $vendorMap[$vendor] = [];
    }
    $vendorMap[$vendor][] = $class;
}

echo "=== Répartition par vendor ===\n";
foreach ($vendorMap as $vendor => $classes) {
    echo "$vendor: " . count($classes) . " directive(s)\n";
    foreach ($classes as $class) {
        echo "  - $class\n";
    }
    echo "\n";
}

// 6. Analyse par package
$packageMap = [];
foreach ($directives as $class) {
    $parts = explode('\\', $class);
    if (count($parts) >= 2) {
        $package = $parts[0] . '/' . $parts[1];
        if (!isset($packageMap[$package])) {
            $packageMap[$package] = [];
        }
        $packageMap[$package][] = $class;
    }
}

echo "=== Répartition par package ===\n";
foreach ($packageMap as $package => $classes) {
    echo "$package: " . count($classes) . " directive(s)\n";
}

// 7. Statistiques
echo "\n=== Statistiques ===\n";
echo "Total packages scannés: " . count($composerReader->getAllDependencies()) . "\n";
echo "Packages avec directives: " . count($packageMap) . "\n";
echo "Directives totales: " . count($directives) . "\n";

// 8. Problèmes rencontrés
$problems = $vendorDiscovery->getProblems();
if ($problems->isNotEmpty()) {
    echo "\n=== Problèmes rencontrés ===\n";
    foreach ($problems as $problem) {
        $key = $problem->get('key');
        $context = $problem->get('context');
        $message = $problem->get('message');
        $timestamp = $problem->get('timestamp');
        echo "  ❌ [$key] $context\n";
        echo "     $message\n";
        echo "     Time: $timestamp\n";
    }
}

// 9. Analyse des chemins
echo "\n=== Analyse des structures ===\n";

$packagesScanned = 0;
$pathsFound = 0;

foreach ($composerReader->getPackageNames() as $package) {
    $packagePath = $composerReader->getVendorDir() . '/' . $package;
    $composerData = json_decode(
        $fileSystem->get($packagePath . '/composer.json'),
        true
    );
    
    if (isset($composerData['autoload']['psr-4'])) {
        $packagesScanned++;
        foreach ($composerData['autoload']['psr-4'] as $namespace => $path) {
            $fullPath = $packagePath . '/' . $path . '/Directives';
            if ($fileSystem->isDirectory($fullPath)) {
                $pathsFound++;
                echo "Package $package: $fullPath\n";
            }
        }
    }
}

echo "\nPackages avec autoload PSR-4: $packagesScanned\n";
echo "Chemins Directives trouvés: $pathsFound\n";

// 10. Configuration personnalisée
echo "\n=== Configuration personnalisée ===\n";

$packagesWithConfig = 0;
foreach ($composerReader->getPackageNames() as $package) {
    $packagePath = $composerReader->getVendorDir() . '/' . $package;
    $configPath = $packagePath . '/config/directive.php';
    
    if ($fileSystem->exists($configPath)) {
        $packagesWithConfig++;
        echo "Package $package a une configuration custom\n";
        
        try {
            $config = require $configPath;
            if (isset($config['custom_sources'])) {
                echo "  Sources personnalisées: " . implode(', ', $config['custom_sources']) . "\n";
            }
        } catch (Throwable $e) {
            echo "  ⚠️ Erreur de lecture de la config\n";
        }
    }
}

echo "\nPackages avec configuration: $packagesWithConfig\n";

// 11. Vérification des directives intégrées
echo "\n=== Vérification des directives intégrées ===\n";
$expected = [
    'AndyDefer\Directive\BuiltIn\ListDirective',
    'AndyDefer\Directive\BuiltIn\HelpDirective',
    'AndyDefer\Directive\BuiltIn\VersionDirective',
    'AndyDefer\Directive\BuiltIn\CleanLogsDirective',
    'AndyDefer\Directive\BuiltIn\KernelAuditDirective',
];

foreach ($expected as $expectedClass) {
    $found = in_array($expectedClass, $directives, true);
    echo ($found ? '✅' : '❌') . " $expectedClass\n";
}
```

## Voir aussi

- `DiscoverySourceInterface` - Interface de source de découverte
- `AbstractDiscovery` - Classe de base avec gestion des problèmes
- `DirectiveClassScanner` - Scanner des classes
- `ComposerReaderService` - Lecture de composer.json
- `DependencyResolverService` - Résolution des dépendances
- `WorkspaceDirectiveDiscovery` - Découverte dans l'espace de travail
- `BuiltInDirectiveDiscovery` - Découverte des directives intégrées