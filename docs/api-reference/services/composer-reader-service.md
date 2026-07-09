# ComposerReaderService - Référence Technique

## Description

Service de lecture et d'accès aux informations du fichier `composer.json`. Fournit une abstraction typée pour interroger les dépendances, la configuration d'autoloading et les métadonnées des packages.

## Hiérarchie / Implémentations

```
ComposerReaderInterface
    └── ComposerReaderService
```

## Rôle principal

`ComposerReaderService` offre une couche d'abstraction pour interagir avec le fichier `composer.json` d'un projet. Il permet de :

- Lire les dépendances de production et de développement
- Récupérer la configuration d'autoloading
- Vérifier la présence d'un package spécifique
- Extraire les noms des vendors
- Mettre en cache les données pour des performances optimales

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `DirectiveConfigInterface` - Configuration du package
- `FileSystemInterface` - Opérations sur le système de fichiers
- PHP 8.1+

## API / Méthodes publiques

### `__construct(DirectiveConfigInterface $config, FileSystemInterface $fileSystem)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `DirectiveConfigInterface` | Configuration du package |
| `$fileSystem` | `FileSystemInterface` | Service de système de fichiers |

**Retourne :** `void`

**Exemple :**
```php
$config = new DirectiveConfig($configRepository);
$fileSystem = new FileSystemService();
$reader = new ComposerReaderService($config, $fileSystem);
```

---

### `getRequire(): array`

Récupère les dépendances de production.

**Retourne :** `array<string, string>` - Tableau [nom du package => contrainte de version]

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$dependencies = $reader->getRequire();
// [
//     'laravel/framework' => '^12.0',
//     'andydefer/domain-structures' => '^1.21',
// ]
```

---

### `getRequireDev(): array`

Récupère les dépendances de développement.

**Retourne :** `array<string, string>` - Tableau [nom du package => contrainte de version]

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$devDependencies = $reader->getRequireDev();
// [
//     'phpunit/phpunit' => '^10.5',
//     'mockery/mockery' => '^1.6',
// ]
```

---

### `getAllDependencies(): array`

Récupère l'ensemble des dépendances (production et développement).

**Retourne :** `array<string, string>` - Tableau [nom du package => contrainte de version]

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$allDeps = $reader->getAllDependencies();
// Combine getRequire() + getRequireDev()
```

---

### `getVendorDirectories(): array`

Récupère la liste des noms de vendors à partir des dépendances de production.

**Retourne :** `array<int, string>` - Liste des vendors uniques

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$vendors = $reader->getVendorDirectories();
// ['laravel', 'andydefer', 'symfony', 'nikic']
```

---

### `getPackageNames(): array`

Récupère la liste de tous les packages de production.

**Retourne :** `array<int, string>` - Liste des noms de packages

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$packages = $reader->getPackageNames();
// [
//     'laravel/framework',
//     'andydefer/domain-structures',
//     'nikic/php-parser',
// ]
```

---

### `hasPackage(string $packageName): bool`

Vérifie si un package spécifique est installé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$packageName` | `string` | Nom du package à vérifier |

**Retourne :** `bool` - `true` si le package existe

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
if ($reader->hasPackage('laravel/framework')) {
    echo "Laravel is installed\n";
}
```

---

### `getPackageVersion(string $packageName): ?string`

Récupère la contrainte de version d'un package spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$packageName` | `string` | Nom du package à interroger |

**Retourne :** `?string` - Contrainte de version ou `null` si le package n'est pas trouvé

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$version = $reader->getPackageVersion('laravel/framework');
// '^12.0'
```

---

### `getAutoload(): array`

Récupère la configuration d'autoloading de production.

**Retourne :** `array<string, mixed>` - Configuration d'autoloading

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$autoload = $reader->getAutoload();
// [
//     'psr-4' => [
//         'App\\' => 'app/',
//         'AndyDefer\\Directive\\' => 'src/',
//     ]
// ]
```

---

### `getAutoloadDev(): array`

Récupère la configuration d'autoloading de développement.

**Retourne :** `array<string, mixed>` - Configuration d'autoloading-dev

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$autoloadDev = $reader->getAutoloadDev();
// [
//     'psr-4' => [
//         'Tests\\' => 'tests/',
//     ]
// ]
```

---

### `getVendorDir(): string`

Récupère le chemin absolu vers le dossier `vendor`.

**Retourne :** `string` - Chemin vers le dossier vendor

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$vendorDir = $reader->getVendorDir();
// '/home/user/project/vendor'
```

---

## Cas d'utilisation

### Cas 1 : Analyse des dépendances installées

```php
<?php

use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Services\ComposerReaderService;
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

echo "=== Dependencies Overview ===\n\n";

echo "Production dependencies:\n";
foreach ($reader->getRequire() as $package => $version) {
    echo "  - $package: $version\n";
}

echo "\nDevelopment dependencies:\n";
foreach ($reader->getRequireDev() as $package => $version) {
    echo "  - $package: $version\n";
}

echo "\nTotal: " . count($reader->getAllDependencies()) . " packages\n";

echo "\nVendors:\n";
foreach ($reader->getVendorDirectories() as $vendor) {
    echo "  - $vendor\n";
}
```

### Cas 2 : Vérification de compatibilité

```php
<?php

$requiredPackages = [
    'laravel/framework' => '^12.0',
    'php' => '^8.1',
];

$allMissing = [];

foreach ($requiredPackages as $package => $requiredVersion) {
    if (!$reader->hasPackage($package)) {
        $allMissing[] = $package;
        continue;
    }
    
    $installedVersion = $reader->getPackageVersion($package);
    echo "$package: installed ($installedVersion), required ($requiredVersion)\n";
}

if (!empty($allMissing)) {
    echo "\nMissing packages:\n";
    foreach ($allMissing as $package) {
        echo "  - $package\n";
    }
}
```

### Cas 3 : Découverte des vendors pour l'analyse de code

```php
<?php

$vendors = $reader->getVendorDirectories();
$vendorPaths = [];

foreach ($vendors as $vendor) {
    $path = $reader->getVendorDir() . '/' . $vendor;
    if (is_dir($path)) {
        $vendorPaths[$vendor] = $path;
        echo "Vendor $vendor found at: $path\n";
    }
}

// Parcourir les packages de chaque vendor
foreach ($reader->getPackageNames() as $package) {
    $vendor = explode('/', $package)[0];
    echo "Package $package belongs to vendor $vendor\n";
}
```

### Cas 4 : Configuration d'autoloading

```php
<?php

$autoload = $reader->getAutoload();

if (isset($autoload['psr-4'])) {
    echo "PSR-4 mappings:\n";
    foreach ($autoload['psr-4'] as $namespace => $path) {
        echo "  $namespace => $path\n";
    }
}

// Vérifier si un namespace est autoloadable
$namespace = 'AndyDefer\\Directive\\';
$psr4Mappings = $autoload['psr-4'] ?? [];

foreach ($psr4Mappings as $nsPrefix => $path) {
    if (str_starts_with($namespace, $nsPrefix)) {
        echo "Namespace $namespace is autoloaded from $path\n";
        break;
    }
}
```

---

## Flux d'exécution

```
getComposerData()
    ↓
Vérifier le cache ($composerData)
    ├── Cache trouvé → retourner
    └── Cache vide → continuer
    ↓
validateComposerFileExists()
    ├── Fichier existe → continuer
    └── Fichier inexistant → RuntimeException
    ↓
readComposerFile()
    ├── Lecture réussie → continuer
    └── Échec de lecture → RuntimeException
    ↓
parseComposerJson()
    ├── JSON valide → stocker dans cache
    └── JSON invalide → RuntimeException
    ↓
Retourner les données décodées
```

### Lecture des dépendances

```
getRequire()/getRequireDev()
    ↓
getComposerData() → données du composer.json
    ↓
Extraire la section 'require' ou 'require-dev'
    ↓
Retourner le tableau ou [] si absent
```

### Extraction des vendors

```
getVendorDirectories()
    ↓
getRequire() → dépendances de production
    ↓
Pour chaque package
    ├── Si package PHP → ignorer
    ├── Extraire le vendor (avant le '/')
    └── Ajouter à la liste
    ↓
Retourner les vendors uniques
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| composer.json introuvable | `RuntimeException` | `composer.json not found at: {path}` |
| Lecture impossible | `RuntimeException` | `Could not read composer.json at: {path}` |
| JSON invalide | `RuntimeException` | `Invalid JSON in composer.json at {path}: {error}` |

**Note :** Toutes les méthodes publiques peuvent lever une `RuntimeException` si le fichier `composer.json` est inaccessible ou invalide.

---

## Intégration

### Avec DirectiveConfig

```php
$configRepo = new ConfigRepository([
    'directive' => [
        'base_path' => '/path/to/project',
        'vendor_dir' => '/path/to/project/vendor',
        'composer_path' => '/path/to/project/composer.json',
    ]
]);

$config = new DirectiveConfig($configRepo);
$reader = new ComposerReaderService($config, $fileSystem);
```

### Dans Laravel (Service Provider)

```php
$this->app->singleton(ComposerReaderInterface::class, function ($app) {
    return new ComposerReaderService(
        $app->make(DirectiveConfigInterface::class),
        $app->make(FileSystemInterface::class)
    );
});
```

### Avec DependencyResolverService

```php
$resolver = new DependencyResolverService($reader, $fileSystem);
$allDependencies = $resolver->resolveAll();
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `getComposerData()` | O(1) | Cache après première lecture |
| `getRequire()` | O(1) | Lecture du cache |
| `getAllDependencies()` | O(n) | n = nombre de dépendances |
| `getVendorDirectories()` | O(n) | n = nombre de packages |
| `hasPackage()` | O(1) | Recherche dans le tableau |

**Optimisations :**
- Mise en cache du contenu de `composer.json`
- Pas de relecture du fichier à chaque appel
- Utilisation de tableaux associatifs pour les recherches O(1)

**Mémoire :**
- Le fichier `composer.json` complet est stocké en mémoire
- Taille typique : 5-20 KB selon le nombre de dépendances

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

try {
    // 2. Récupérer toutes les dépendances
    $allDependencies = $reader->getAllDependencies();
    echo "=== All Dependencies ===\n";
    echo "Total: " . count($allDependencies) . " packages\n\n";
    
    // 3. Afficher les dépendances de production
    echo "=== Production Dependencies ===\n";
    foreach ($reader->getRequire() as $package => $version) {
        echo "  - $package: $version\n";
    }
    
    // 4. Afficher les dépendances de développement
    echo "\n=== Development Dependencies ===\n";
    foreach ($reader->getRequireDev() as $package => $version) {
        echo "  - $package: $version\n";
    }
    
    // 5. Liste des vendors
    echo "\n=== Vendors ===\n";
    foreach ($reader->getVendorDirectories() as $vendor) {
        echo "  - $vendor\n";
    }
    
    // 6. Vérification d'un package spécifique
    $package = 'laravel/framework';
    if ($reader->hasPackage($package)) {
        $version = $reader->getPackageVersion($package);
        echo "\n✅ $package is installed (version: $version)\n";
    } else {
        echo "\n❌ $package is not installed\n";
    }
    
    // 7. Configuration d'autoloading
    echo "\n=== Autoloading ===\n";
    $autoload = $reader->getAutoload();
    if (isset($autoload['psr-4'])) {
        echo "PSR-4 mappings:\n";
        foreach ($autoload['psr-4'] as $namespace => $path) {
            echo "  - $namespace => $path\n";
        }
    }
    
    // 8. Liste des packages
    echo "\n=== Package Names ===\n";
    foreach ($reader->getPackageNames() as $package) {
        echo "  - $package\n";
    }
    
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
```

## Voir aussi

- `DependencyResolverService` - Résolution récursive des dépendances
- `DirectiveConfig` - Configuration du package
- `FileSystemService` - Service de système de fichiers
- `ComposerReaderInterface` - Interface du lecteur