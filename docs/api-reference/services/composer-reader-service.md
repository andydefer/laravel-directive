# ComposerReaderService - Référence Technique

## Description

Service de lecture et d'accès aux informations des packages Composer. Fournit une abstraction typée sur le fichier `composer.json`, permettant de récupérer les dépendances, la configuration d'autoloading et les métadonnées des packages.

## Hiérarchie / Implémentations

```
ComposerReaderInterface
    └── ComposerReaderService (final)
```

## Rôle principal

Centraliser l'accès au fichier `composer.json` du projet en fournissant une API typée et sécurisée. Le service gère la lecture, le parsing, la mise en cache et la validation des données du fichier Composer.

## Installation

### Dépendances

```bash
# Le service est automatiquement disponible via le conteneur
composer require andydefer/laravel-directive
```

### Configuration dans le conteneur

```php
// Dans le service provider
$this->app->singleton(ComposerReaderInterface::class, function ($app) {
    return new ComposerReaderService(
        $app->make(DirectiveConfigInterface::class),
        $app->make(FileSystemInterface::class)
    );
});
```

## API / Méthodes publiques

### `getRequire(): array`

Récupère les dépendances de production du fichier `composer.json`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, string>` - Tableau associatif [nom du package → contrainte de version]

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$dependencies = $composerReader->getRequire();
// ['laravel/framework' => '^10.0', 'php' => '^8.1']
```

---

### `getRequireDev(): array`

Récupère les dépendances de développement du fichier `composer.json`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, string>` - Tableau associatif [nom du package → contrainte de version]

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$devDependencies = $composerReader->getRequireDev();
// ['phpunit/phpunit' => '^10.0', 'pestphp/pest' => '^2.0']
```

---

### `getAllDependencies(): array`

Récupère toutes les dépendances (production + développement).

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, string>` - Tableau associatif de toutes les dépendances

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$allDependencies = $composerReader->getAllDependencies();
// ['laravel/framework' => '^10.0', 'phpunit/phpunit' => '^10.0']
```

---

### `getVendorDirectories(): array`

Récupère la liste des noms de vendors depuis les dépendances de production.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des noms de vendors (uniques)

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$vendors = $composerReader->getVendorDirectories();
// ['laravel', 'symfony', 'monolog']
```

---

### `getPackageNames(): array`

Récupère la liste des noms de packages (production uniquement).

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des noms de packages

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$packages = $composerReader->getPackageNames();
// ['laravel/framework', 'symfony/console', 'monolog/monolog']
```

---

### `hasPackage(string $packageName): bool`

Vérifie si un package spécifique est installé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$packageName` | `string` | Le nom du package à vérifier (ex: "laravel/framework") |

**Retourne :** `bool` - `true` si le package existe, `false` sinon

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

if ($composerReader->hasPackage('laravel/framework')) {
    echo "Laravel est installé";
}
```

---

### `getPackageVersion(string $packageName): ?string`

Récupère la contrainte de version d'un package spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$packageName` | `string` | Le nom du package à interroger |

**Retourne :** `string|null` - La contrainte de version, ou `null` si le package n'est pas trouvé

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$version = $composerReader->getPackageVersion('laravel/framework');
// '^10.0'

if ($version === null) {
    echo "Package non installé";
}
```

---

### `getAutoload(): array`

Récupère la configuration d'autoloading de production.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, mixed>` - La configuration d'autoloading

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$autoload = $composerReader->getAutoload();
// ['psr-4' => ['App\\' => 'app/'], 'classmap' => ['database/']]
```

---

### `getAutoloadDev(): array`

Récupère la configuration d'autoloading de développement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, mixed>` - La configuration d'autoloading-dev

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$autoloadDev = $composerReader->getAutoloadDev();
// ['psr-4' => ['Tests\\' => 'tests/']]
```

---

### `getVendorDir(): string`

Récupère le chemin absolu du répertoire vendor.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - Le chemin absolu du répertoire vendor

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$vendorDir = $composerReader->getVendorDir();
// '/var/www/project/vendor'
```

## Cas d'utilisation

### Cas 1 : Vérification des dépendances requises

```php
<?php

use AndyDefer\Directive\Services\ComposerReaderService;

$requiredPackages = [
    'laravel/framework',
    'symfony/console',
    'monolog/monolog',
];

$missing = [];

foreach ($requiredPackages as $package) {
    if (!$composerReader->hasPackage($package)) {
        $missing[] = $package;
    }
}

if (!empty($missing)) {
    throw new RuntimeException(
        'Missing required packages: ' . implode(', ', $missing)
    );
}
```

### Cas 2 : Analyse des dépendances pour la découverte

```php
<?php

// Dans VendorDirectiveDiscovery
$packages = $composerReader->getPackageNames();

foreach ($packages as $package) {
    $vendor = $composerReader->getVendorDir() . '/' . $package;
    
    if (is_dir($vendor . '/src/Directives')) {
        echo "Package {$package} contient des directives\n";
    }
}
```

### Cas 3 : Génération d'un rapport de dépendances

```php
<?php

$dependencies = $composerReader->getAllDependencies();

echo "=== Rapport des dépendances ===\n";
echo "Total: " . count($dependencies) . " packages\n\n";

foreach ($dependencies as $package => $version) {
    $isDev = array_key_exists($package, $composerReader->getRequireDev());
    $type = $isDev ? 'DEV' : 'PROD';
    
    echo "[{$type}] {$package} : {$version}\n";
}
```

### Cas 4 : Configuration d'autoloading personnalisée

```php
<?php

$autoload = $composerReader->getAutoload();

if (isset($autoload['psr-4'])) {
    foreach ($autoload['psr-4'] as $namespace => $path) {
        echo "Namespace: {$namespace} → Path: {$path}\n";
    }
}
```

## Flux d'exécution

```
ComposerReaderService::getComposerData()
    │
    ├── Vérifie le cache ($composerData)
    │   └── Si présent → retourne
    │
    ├── $composerPath = $config->getComposerPath()
    │
    ├── validateComposerFileExists()
    │   └── Si non existant → RuntimeException
    │
    ├── readComposerFile()
    │   ├── $fileSystem->get()
    │   └── Si erreur → RuntimeException
    │
    ├── parseComposerJson()
    │   ├── json_decode()
    │   └── Si JSON invalide → RuntimeException
    │
    └── Mise en cache → retourne les données
```

## Structure du composer.json analysé

```json
{
    "require": {
        "laravel/framework": "^10.0",
        "symfony/console": "^6.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fichier composer.json inexistant | `RuntimeException` | `composer.json not found at: {path}` |
| Fichier non lisible | `RuntimeException` | `Could not read composer.json at: {path}` |
| JSON invalide | `RuntimeException` | `Invalid JSON in composer.json at {path}: {error}` |
| Package PHP (php, php-64bit) | Ignoré | - |
| Format de package invalide | Ignoré | - |

### Exceptions détaillées

```php
// Exemple 1: Fichier manquant
composer.json not found at: /var/www/project/composer.json

// Exemple 2: JSON invalide
Invalid JSON in composer.json at /var/www/project/composer.json: Syntax error

// Exemple 3: Erreur de lecture
Could not read composer.json at: /var/www/project/composer.json
```

## Intégration

Le `ComposerReaderService` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `DirectiveConfigInterface` | Fournit le chemin du fichier composer.json |
| `FileSystemInterface` | Opérations de lecture de fichiers |
| `VendorDirectiveDiscovery` | Utilisé pour découvrir les packages vendors |
| `DependencyResolverService` | Utilisé pour résoudre l'arbre des dépendances |

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(1) | Lecture et parsing du fichier JSON |
| Cache | ✅ Oui | Les données sont mises en cache après la première lecture |
| Temps typique | 5-20ms | Première lecture, puis <1ms (cache) |
| Mémoire | ~100KB | Dépend de la taille du fichier composer.json |

### Stratégie de cache

```php
private ?array $composerData = null;

private function getComposerData(): array
{
    if ($this->composerData !== null) {
        return $this->composerData; // ✅ Cache hit
    }
    
    // Chargement et mise en cache
    $this->composerData = $this->loadComposerData();
    return $this->composerData;
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| Composer 2.x | ✅ Complet | - |
| Composer 1.x | ⚠️ Limité | `composer.json` version 1 supporté |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Config\Repository as ConfigRepository;

// Créer les dépendances
$configRepository = new ConfigRepository([
    'directive' => [
        'base_path' => '/var/www/project'
    ]
]);

$config = new DirectiveConfig($configRepository);
$fileSystem = new FileSystemService();

// Créer le service
$composerReader = new ComposerReaderService($config, $fileSystem);

// Utiliser le service
echo "=== Informations Composer ===\n\n";

// Dépendances de production
$prod = $composerReader->getRequire();
echo "Dépendances PROD (" . count($prod) . "):\n";
foreach ($prod as $package => $version) {
    echo "- {$package}: {$version}\n";
}

// Dépendances de développement
$dev = $composerReader->getRequireDev();
echo "\nDépendances DEV (" . count($dev) . "):\n";
foreach ($dev as $package => $version) {
    echo "- {$package}: {$version}\n";
}

// Toutes les dépendances
$all = $composerReader->getAllDependencies();
echo "\nTotal dépendances: " . count($all) . "\n";

// Vérification d'un package
if ($composerReader->hasPackage('laravel/framework')) {
    $version = $composerReader->getPackageVersion('laravel/framework');
    echo "\n✅ Laravel installé (version: {$version})\n";
}

// Autoloading
$autoload = $composerReader->getAutoload();
if (isset($autoload['psr-4'])) {
    echo "\n=== Autoload PSR-4 ===\n";
    foreach ($autoload['psr-4'] as $namespace => $path) {
        echo "- {$namespace} → {$path}\n";
    }
}

// Vendor directory
$vendorDir = $composerReader->getVendorDir();
echo "\n📁 Vendor directory: {$vendorDir}\n";
```

## Notes techniques

### Packages PHP ignorés

Les packages commençant par `php` sont automatiquement ignorés par les méthodes `getPackageNames()` et `getVendorDirectories()` :

```php
// Ces packages sont ignorés
- php (meta-package)
- php-64bit
- php-80 (extension)
- php-81 (extension)
```

### Format des packages

Le service attend un format de package standard : `vendor/package`.

```php
// ✅ Format valide
'laravel/framework'
'symfony/console'
'monolog/monolog'

// ❌ Format invalide
'laravel'              // Pas de vendor
'laravel/framework/'   // Slash final
'/laravel/framework'   // Slash initial
```

### Gestion des versions

Les versions sont retournées telles quelles, sans parsing :

```php
// Exemples de versions retournées
- '^10.0'
- '~6.0'
- '>=7.0'
- 'dev-master'
- '1.2.3'
```

### Bonnes pratiques

1. **Utiliser le cache** : Le service gère automatiquement le cache
2. **Vérifier l'existence** : Utiliser `hasPackage()` avant `getPackageVersion()`
3. **Gérer les exceptions** : Toujours capturer `RuntimeException` lors des opérations
4. **Validation des packages** : Vérifier que les packages ont le format `vendor/package`

---