# ComposerReaderService - Référence Technique

## Description

Service de lecture et d'analyse du fichier `composer.json` d'un projet PHP. Permet d'extraire les dépendances, les configurations d'autoload et les métadonnées du package.

## Hiérarchie / Implémentations

```
ComposerReaderInterface
    └── ComposerReaderService
```

## Rôle principal

`ComposerReaderService` fournit une interface structurée pour interroger le fichier `composer.json` d'un projet. Il expose des méthodes pour récupérer les dépendances (require, require-dev), les configurations d'autoload, et vérifier l'existence de packages spécifiques.

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `FileSystemInterface` - Pour les opérations sur le système de fichiers
- PHP 8.1+

## API / Méthodes publiques

### `__construct(string $basePath, FileSystemInterface $fileSystem)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$basePath` | `string` | Chemin racine du projet contenant `composer.json` |
| `$fileSystem` | `FileSystemInterface` | Service de système de fichiers pour les opérations I/O |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$fileSystem = new FileSystemService();
$reader = new ComposerReaderService('/path/to/project', $fileSystem);
```

---

### `getRequire(): array`

Retourne les dépendances de production (section `require`).

**Retourne :** `array<string, string>` - Tableau associatif [nom du package => version]

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$dependencies = $reader->getRequire();
// ['andydefer/domain-structures' => '^1.0', 'laravel/framework' => '^10.0']
```

---

### `getRequireDev(): array`

Retourne les dépendances de développement (section `require-dev`).

**Retourne :** `array<string, string>` - Tableau associatif [nom du package => version]

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$devDependencies = $reader->getRequireDev();
// ['phpunit/phpunit' => '^10.0', 'mockery/mockery' => '^1.6']
```

---

### `getAllDependencies(): array`

Fusionne les dépendances de production et de développement.

**Retourne :** `array<string, string>` - Tableau associatif [nom du package => version]

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$all = $reader->getAllDependencies();
// Combine require + require-dev
```

---

### `getVendorDirectories(): array`

Extrait les noms des vendors (première partie du nom du package).

**Retourne :** `array<string>` - Liste des noms de vendors uniques

**Exceptions :** Aucune

**Exemple :**
```php
$vendors = $reader->getVendorDirectories();
// ['andydefer', 'laravel', 'phpunit']
```

---

### `getPackageNames(): array`

Retourne la liste des noms de packages de production.

**Retourne :** `array<string>` - Liste des noms de packages

**Exceptions :** Aucune

**Exemple :**
```php
$packages = $reader->getPackageNames();
// ['andydefer/domain-structures', 'laravel/framework']
```

---

### `hasPackage(string $packageName): bool`

Vérifie si un package est présent dans les dépendances (production ou développement).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$packageName` | `string` | Nom complet du package (ex: `andydefer/domain-structures`) |

**Retourne :** `bool` - `true` si le package existe

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
if ($reader->hasPackage('andydefer/domain-structures')) {
    echo "Package found!";
}
```

---

### `getPackageVersion(string $packageName): ?string`

Retourne la version d'un package spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$packageName` | `string` | Nom complet du package |

**Retourne :** `?string` - La version du package ou `null` s'il n'existe pas

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$version = $reader->getPackageVersion('andydefer/domain-structures');
// '^1.0'
```

---

### `getAutoload(): array`

Retourne la configuration d'autoload (section `autoload`).

**Retourne :** `array` - Configuration d'autoload

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$autoload = $reader->getAutoload();
// ['psr-4' => ['AndyDefer\\' => 'src/']]
```

---

### `getAutoloadDev(): array`

Retourne la configuration d'autoload de développement (section `autoload-dev`).

**Retourne :** `array` - Configuration d'autoload de développement

**Exceptions :** `RuntimeException` - Si `composer.json` est introuvable ou invalide

**Exemple :**
```php
$autoloadDev = $reader->getAutoloadDev();
// ['psr-4' => ['AndyDefer\\Tests\\' => 'tests/']]
```

---

### `getVendorDir(): string`

Retourne le chemin du dossier `vendor`.

**Retourne :** `string` - Chemin absolu vers le dossier vendor

**Exceptions :** Aucune

**Exemple :**
```php
$vendorDir = $reader->getVendorDir();
// '/path/to/project/vendor'
```

---

## Cas d'utilisation

### Cas 1 : Analyse des dépendances d'un projet

```php
<?php

use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\PhpServices\Services\FileSystemService;

$fileSystem = new FileSystemService();
$reader = new ComposerReaderService(getcwd(), $fileSystem);

$dependencies = $reader->getRequire();

foreach ($dependencies as $package => $version) {
    echo "Package: $package (version: $version)\n";
}

if ($reader->hasPackage('laravel/framework')) {
    $version = $reader->getPackageVersion('laravel/framework');
    echo "Laravel version: $version\n";
}
```

### Cas 2 : Découverte des vendors pour l'auto-discovery

```php
<?php

$vendors = $reader->getVendorDirectories();

foreach ($vendors as $vendor) {
    $vendorPath = $reader->getVendorDir() . '/' . $vendor;
    if ($fileSystem->isDirectory($vendorPath)) {
        echo "Scanning vendor: $vendor\n";
        // Scanner les packages du vendor
    }
}
```

### Cas 3 : Validation de configuration

```php
<?php

$autoload = $reader->getAutoload();

if (!isset($autoload['psr-4'])) {
    throw new RuntimeException('PSR-4 autoload not configured');
}

foreach ($autoload['psr-4'] as $namespace => $path) {
    echo "Namespace: $namespace -> $path\n";
}
```

---

## Flux d'exécution

```
__construct($basePath, $fileSystem)
    ↓
getComposerData()
    ├── Vérifier l'existence du fichier
    ├── Lire le contenu
    ├── Décoder le JSON
    └── Mettre en cache
    ↓
Méthodes publiques
    ├── getRequire() → $data['require'] ?? []
    ├── getRequireDev() → $data['require-dev'] ?? []
    ├── getAutoload() → $data['autoload'] ?? []
    ├── getAutoloadDev() → $data['autoload-dev'] ?? []
    └── getAllDependencies() → merge(require, require-dev)
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| composer.json introuvable | `RuntimeException` | `composer.json not found at: {path}` |
| Impossible de lire le fichier | `RuntimeException` | `Could not read composer.json at: {path}` |
| JSON invalide | `RuntimeException` | `Invalid JSON in composer.json: {error}` |

---

## Intégration

### Avec DependencyResolverService

```php
$reader = new ComposerReaderService(getcwd(), $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem);

$allDependencies = $resolver->resolveAll();
```

### Avec un framework Laravel

```php
// Dans un ServiceProvider
$this->app->singleton(ComposerReaderInterface::class, function ($app) {
    return new ComposerReaderService(
        base_path(),
        $app->make(FileSystemInterface::class)
    );
});
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `getComposerData()` | O(1) | Lecture et décodage JSON, mise en cache |
| `getRequire()` | O(1) | Accès direct au tableau |
| `getAllDependencies()` | O(n) | Fusion de deux tableaux |
| `getVendorDirectories()` | O(n) | Parcours des dépendances |
| `hasPackage()` | O(1) | Recherche dans le tableau |
| `getPackageVersion()` | O(1) | Recherche dans le tableau |

**Optimisations :**
- Mise en cache du contenu de `composer.json`
- Pas de relecture du fichier à chaque appel

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

use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\PhpServices\Services\FileSystemService;

$fileSystem = new FileSystemService();
$reader = new ComposerReaderService(getcwd(), $fileSystem);

// Récupérer toutes les dépendances
$allDependencies = $reader->getAllDependencies();

echo "=== Dependencies ===\n";
foreach ($allDependencies as $package => $version) {
    $type = isset($reader->getRequire()[$package]) ? 'require' : 'require-dev';
    echo "[$type] $package: $version\n";
}

// Récupérer les vendors
$vendors = $reader->getVendorDirectories();
echo "\n=== Vendors ===\n";
foreach ($vendors as $vendor) {
    echo "- $vendor\n";
}

// Vérifier un package spécifique
if ($reader->hasPackage('laravel/framework')) {
    echo "\nLaravel version: " . $reader->getPackageVersion('laravel/framework');
}

// Configuration d'autoload
$autoload = $reader->getAutoload();
echo "\n=== Autoload ===\n";
foreach ($autoload['psr-4'] ?? [] as $namespace => $path) {
    echo "$namespace -> $path\n";
}
```

## Voir aussi

- `DependencyResolverService` - Résolution récursive des dépendances
- `FileSystemService` - Service de système de fichiers
- `ComposerReaderInterface` - Interface du service
---