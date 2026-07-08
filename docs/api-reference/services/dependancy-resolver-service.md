# DependencyResolverService - Référence Technique

## Description

Service de résolution récursive des dépendances Composer. Analyse l'arborescence complète des dépendances d'un projet à partir du fichier `composer.json`.

## Hiérarchie / Implémentations

```
DependencyResolverInterface
    └── DependencyResolverService
```

## Rôle principal

`DependencyResolverService` parcourt récursivement toutes les dépendances d'un projet en lisant les fichiers `composer.json` de chaque package installé dans le dossier `vendor`. Il permet de :

- Résoudre l'intégralité de l'arbre des dépendances
- Détecter les dépendances circulaires
- Obtenir une vue plate ou arborescente des dépendances

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `ComposerReaderInterface` - Lecture du `composer.json` racine
- `FileSystemInterface` - Opérations sur le système de fichiers
- PHP 8.1+

## API / Méthodes publiques

### `__construct(ComposerReaderInterface $composerReader, FileSystemInterface $fileSystem)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$composerReader` | `ComposerReaderInterface` | Service de lecture du composer.json racine |
| `$fileSystem` | `FileSystemInterface` | Service de système de fichiers |

**Retourne :** `void`

**Exemple :**
```php
$reader = new ComposerReaderService(getcwd(), $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem);
```

---

### `resolveAll(): array`

Résout toutes les dépendances du projet de manière récursive.

**Retourne :** `array<string, array>` - Tableau associatif [nom du package => données du composer.json]

**Exceptions :** Aucune (les erreurs sont ignorées silencieusement)

**Exemple :**
```php
$dependencies = $resolver->resolveAll();
// [
//     'andydefer/domain-structures' => [...],
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

**Retourne :** `array<string, array>` - Dépendances du package

**Exceptions :** Aucune

**Exemple :**
```php
$deps = $resolver->resolvePackageDependencies('andydefer/domain-structures');
// ['andydefer/php-vo' => [...], 'andydefer/php-utils' => [...]]
```

---

### `getDependencyTree(): array`

Retourne l'arbre complet des dépendances sous forme hiérarchique.

**Retourne :** `array<string, array>` - Arbre des dépendances

**Exceptions :** Aucune

**Exemple :**
```php
$tree = $resolver->getDependencyTree();
// [
//     'andydefer/domain-structures' => [
//         'andydefer/php-vo' => [
//             'andydefer/php-utils' => []
//         ]
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
}
```

---

## Cas d'utilisation

### Cas 1 : Analyse complète des dépendances

```php
<?php

use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\PhpServices\Services\FileSystemService;

$fileSystem = new FileSystemService();
$reader = new ComposerReaderService(getcwd(), $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem);

$allDependencies = $resolver->resolveAll();

foreach ($allDependencies as $package => $data) {
    echo "Package: $package\n";
    echo "Version: " . ($data['version'] ?? 'unknown') . "\n";
    echo "Requires: " . implode(', ', array_keys($data['require'] ?? [])) . "\n";
    echo "---\n";
}
```

### Cas 2 : Détection des dépendances circulaires

```php
<?php

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
    }
}
```

### Cas 3 : Génération d'un rapport de dépendances

```php
<?php

$packages = $resolver->getFlatDependencies();

echo "Total packages: " . $packages->count() . "\n";
echo "Packages:\n";

foreach ($packages as $package) {
    echo "- $package\n";
}

// Vérifier un package spécifique
if ($packages->contains('andydefer/domain-structures')) {
    echo "\ndomain-structures is present in the project";
}
```

---

## Flux d'exécution

```
resolveAll()
    ↓
getRequire() (via ComposerReader)
    ↓
Pour chaque package racine
    ↓
resolvePackage($package)
    ├── Vérifier si déjà visité
    ├── Marquer comme visité
    ├── Lire composer.json du package
    ├── Stocker les données
    └── Résoudre récursivement les dépendances
    ↓
Retourner tous les packages résolus
```

### Détection des cycles

```
detectCycle($package, $path)
    ├── Si $package dans $path → cycle détecté
    ├── Ajouter $package au $path
    ├── Lire composer.json du package
    ├── Pour chaque dépendance
    │   └── detectCycle($dependency, $path)
    └── Retourner false (aucun cycle)
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

---

## Intégration

### Avec ComposerReaderService

```php
$reader = new ComposerReaderService(getcwd(), $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem);
```

### Dans un framework Laravel

```php
// ServiceProvider
$this->app->singleton(DependencyResolverInterface::class, function ($app) {
    return new DependencyResolverService(
        $app->make(ComposerReaderInterface::class),
        $app->make(FileSystemInterface::class)
    );
});
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `resolveAll()` | O(n²) | n = nombre de packages |
| `resolvePackageDependencies()` | O(n) | Résolution d'un package |
| `getDependencyTree()` | O(n²) | Construction récursive de l'arbre |
| `getFlatDependencies()` | O(n) | Aplatissement des résultats |
| `hasCircularDependency()` | O(n²) | Détection de cycles |

**Optimisations :**
- Visite unique des packages (évite les boucles infinies)
- Pas de relecture des fichiers déjà visités
- Les dépendances sont mises en cache dans `$resolved`

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
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\PhpServices\Services\FileSystemService;

$fileSystem = new FileSystemService();
$reader = new ComposerReaderService(getcwd(), $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem);

// 1. Résoudre toutes les dépendances
$allDependencies = $resolver->resolveAll();
echo "=== All Dependencies ===\n";
echo "Total: " . count($allDependencies) . " packages\n\n";

// 2. Afficher les dépendances directes
$directDependencies = $reader->getRequire();
echo "=== Direct Dependencies ===\n";
foreach ($directDependencies as $package => $version) {
    $deps = $resolver->resolvePackageDependencies($package);
    echo "$package ($version) depends on " . count($deps) . " package(s)\n";
}

// 3. Obtenir l'arbre
$tree = $resolver->getDependencyTree();
echo "\n=== Dependency Tree ===\n";
print_r($tree);

// 4. Collection plate
$flatPackages = $resolver->getFlatDependencies();
echo "\n=== Flat List ===\n";
echo "Packages: " . implode(', ', $flatPackages->toArray()) . "\n";

// 5. Vérifier les cycles
if ($resolver->hasCircularDependency()) {
    echo "\n⚠️ Circular dependency detected!\n";
} else {
    echo "\n✅ No circular dependencies found\n";
}

// 6. Vérifier un package spécifique
$packages = $resolver->getFlatDependencies();
if ($packages->contains('andydefer/domain-structures')) {
    echo "\ndomain-structures is installed\n";
}
```

## Voir aussi

- `ComposerReaderService` - Lecture du composer.json
- `ComposerReaderInterface` - Interface du lecteur
- `FileSystemService` - Service de système de fichiers
- `StringTypedCollection` - Collection typée de chaînes
---