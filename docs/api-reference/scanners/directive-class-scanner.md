# DirectiveClassScanner - Référence Technique

## Description

Scanner qui analyse des fichiers PHP pour découvrir les classes de directives. Il utilise l'AST (Abstract Syntax Tree) pour détecter de manière fiable les classes qui étendent `AbstractDirective`, même avec une syntaxe complexe ou des imports aliasés.

## Hiérarchie / Implémentations

```
DirectiveScannerInterface
    └── DirectiveClassScanner (final)
```

## Rôle principal

Parcourir récursivement les répertoires, analyser les fichiers PHP, et identifier les classes qui sont des directives valides (non abstraites, étendant `AbstractDirective`). Il retourne la liste des FQCN (Fully Qualified Class Names) de toutes les directives trouvées.

## Installation

### Dépendances

```bash
composer require nikic/php-parser
```

### Configuration dans le conteneur

```php
// Dans le service provider
$this->app->singleton(DirectiveScannerInterface::class, function ($app) {
    $fileSystem = $app->make(FileSystemInterface::class);
    $parser = $app->make(Parser::class);
    
    return new DirectiveClassScanner($fileSystem, $parser);
});
```

## API / Méthodes publiques

### `scan(string $directory, int $maxDepth = 3): array`

Scanne un répertoire récursivement pour trouver les classes de directives.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directory` | `string` | Le chemin du répertoire à scanner |
| `$maxDepth` | `int` | Profondeur maximale de récursion (défaut: 3) |

**Retourne :** `array<int, string>` - Liste des FQCN des directives trouvées

**Exceptions :** Aucune (les erreurs de lecture ou de parsing sont ignorées)

**Exemple :**
```php
<?php

use AndyDefer\Directive\Scanners\DirectiveClassScanner;

$scanner = new DirectiveClassScanner($fileSystem, $parser);
$directives = $scanner->scan('/var/www/project/app/Directives', 3);

// Retourne : ['App\Directives\UserDirective', 'App\Directives\AdminDirective']
```

## Cas d'utilisation

### Cas 1 : Scan des directives de l'application

```php
<?php

use AndyDefer\Directive\Scanners\DirectiveClassScanner;

// Scanner les directives du projet
$scanner = new DirectiveClassScanner($fileSystem, $parser);
$directives = $scanner->scan('/var/www/project/app/Directives', 3);

foreach ($directives as $fqcn) {
    echo "Directive trouvée : " . $fqcn . PHP_EOL;
}
```

### Cas 2 : Scan des directives d'un package vendor

```php
<?php

$vendorPath = '/var/www/project/vendor/mon-package/src';
$directives = $scanner->scan($vendorPath . '/Directives', 2);

// Retourne les directives du package
```

### Cas 3 : Scan avec profondeur limitée

```php
<?php

// Scan uniquement sur 2 niveaux de profondeur
$directives = $scanner->scan('/var/www/project/app', 2);

// Structure : app/
//   Directives/          <- Niveau 1 (scanné)
//     UserDirective.php  <- Niveau 2 (scanné)
//     Admin/
//       AdminDirective.php <- Niveau 3 (IGNORÉ)
```

## Flux d'exécution

```
DirectiveClassScanner::scan($directory, $maxDepth)
    │
    ├── Vérifie que le répertoire existe
    │
    └── scanDirectory()
        │
        ├── Pour chaque fichier *.php
        │   ├── analyzeFile()
        │   │   ├── Parser::parse() → AST
        │   │   ├── Traverse l'AST avec le visitor
        │   │   │   ├── Capture du namespace
        │   │   │   ├── Capture des use (aliases)
        │   │   │   ├── Analyse des classes
        │   │   │   │   ├── Vérifie l'héritage AbstractDirective
        │   │   │   │   ├── Vérifie non-abstraite
        │   │   │   │   └── Construction du FQCN
        │   │   │   └── Retourne la liste des classes trouvées
        │   │   └── Retourne les FQCN
        │   └── Merge dans le tableau résultat
        │
        └── Pour chaque sous-répertoire (si profondeur < maxDepth)
            └── Appel récursif de scanDirectory()
```

## Détection des directives

### Critères de validation

Une classe est considérée comme une directive si :

1. ✅ **Non abstraite** : `!$node->isAbstract()`
2. ✅ **Étend AbstractDirective** : `$node->extends === AbstractDirective::class` ou via alias
3. ✅ **A un namespace** : `$this->currentNamespace !== null`

### Gestion des alias (use)

```php
use AndyDefer\Directive\AbstractDirective;

class MyDirective extends AbstractDirective // ✅ Détecté
{
    // ...
}
```

```php
use AndyDefer\Directive\AbstractDirective as BaseDirective;

class MyDirective extends BaseDirective // ✅ Détecté via alias
{
    // ...
}
```

### Syntaxe supportée

| Syntaxe | Support |
|---------|---------|
| `class MyDirective extends AbstractDirective` | ✅ |
| `class MyDirective extends \AndyDefer\Directive\AbstractDirective` | ✅ |
| `class MyDirective extends AbstractDirective { ... }` | ✅ |
| `final class MyDirective extends AbstractDirective` | ✅ |
| `readonly class MyDirective extends AbstractDirective` | ✅ (PHP 8.2+) |
| `class MyDirective extends AbstractDirective implements Interface` | ✅ |
| `abstract class AbstractDirective extends ...` | ❌ (ignoré) |
| `class MyDirective extends OtherClass` | ❌ (ignoré) |
| `class MyDirective { ... }` | ❌ (ignoré) |

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Répertoire inexistant | Retourne un tableau vide | - |
| Fichier PHP invalide | Ignoré silencieusement | - |
| Erreur de parsing AST | Ignoré silencieusement | - |
| Erreur de lecture fichier | Ignoré silencieusement | - |
| Classe abstraite | Ignorée (non ajoutée) | - |
| Classe n'étendant pas AbstractDirective | Ignorée | - |
| Fichier sans namespace | Ignoré | - |

### Pourquoi les erreurs sont ignorées ?

Le scanner ignore silencieusement les erreurs pour :
1. **Robustesse** : Un fichier mal formé ne doit pas bloquer le scan complet
2. **Performance** : Évite les arrêts coûteux
3. **Pratique** : Les fichiers PHP invalides sont rares dans un projet fonctionnel

## Intégration

La classe `DirectiveClassScanner` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `FileSystemInterface` | Opérations sur le système de fichiers |
| `Parser` (nikic/php-parser) | Analyse AST des fichiers PHP |
| `DirectiveDiscoveryService` | Utilisée par le service de découverte |
| `WorkspaceDirectiveDiscovery` | Scan du workspace |
| `VendorDirectiveDiscovery` | Scan des packages vendors |

### Utilisation dans le service de découverte

```php
class DirectiveDiscoveryService
{
    public function discover(): DirectiveMetadataCollection
    {
        // Scan des sources
        $fqcns = $this->scanner->scan('/var/www/project/app/Directives');
        
        foreach ($fqcns as $fqcn) {
            $this->addDirective($fqcn);
        }
    }
}
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(n × m) | n = fichiers PHP, m = profondeur |
| Temps typique | 50-200ms | Pour 50-100 fichiers |
| Mémoire | 1-5 MB | Dépend de la taille des fichiers |
| Cache | ❌ Non | Recommandé d'ajouter un cache |

### Facteurs de performance

1. **Nombre de fichiers** : Plus il y a de fichiers, plus le scan est lent
2. **Profondeur de récursion** : Plus la profondeur est grande, plus le scan est lent
3. **Taille des fichiers** : Les gros fichiers prennent plus de temps à parser
4. **Parser** : L'AST parsing est plus lent que les regex mais plus fiable

### Optimisations recommandées

```php
class DirectiveClassScanner
{
    private ?array $cache = null;
    
    public function scan(string $directory, int $maxDepth = 3): array
    {
        $cacheKey = md5($directory . $maxDepth);
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $this->cache[$cacheKey] = $this->doScan($directory, $maxDepth);
        return $this->cache[$cacheKey];
    }
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | Support `readonly` classes |
| PHP 8.3+ | ✅ Complet | - |
| nikic/php-parser 4.x | ✅ Complet | - |
| nikic/php-parser 5.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;
use PhpParser\ParserFactory;

// Créer les dépendances
$fileSystem = new FileSystemService();
$parser = (new ParserFactory())->createForNewestSupportedVersion();

// Créer le scanner
$scanner = new DirectiveClassScanner($fileSystem, $parser);

// Scanner un répertoire
$directives = $scanner->scan('/var/www/project/app/Directives', 3);

// Afficher les résultats
echo "Directives trouvées : " . count($directives) . PHP_EOL;

foreach ($directives as $fqcn) {
    echo "- " . $fqcn . PHP_EOL;
    
    // Vérification supplémentaire
    $reflection = new ReflectionClass($fqcn);
    if ($reflection->isSubclassOf(AbstractDirective::class)) {
        $instance = $reflection->newInstanceWithoutConstructor();
        echo "  Signature: " . $instance->getSignature() . PHP_EOL;
    }
}

// Exemple avec profondeur personnalisée
$shallowScan = $scanner->scan('/var/www/project/app/Directives', 1);
// Ne scanne que le dossier immédiat, pas les sous-dossiers

// Exemple avec répertoire inexistant
$empty = $scanner->scan('/var/www/project/inexistant', 3);
// Retourne [] sans erreur
```

## Notes techniques

### Pourquoi l'AST plutôt que les regex ?

| Approche | Avantages | Inconvénients |
|----------|-----------|---------------|
| **Regex** | Rapide, simple | Fragile, échoue sur syntaxe complexe |
| **AST** | Fiable, robuste | Plus lent, dépendance externe |

### Limitations connues

1. **Fichiers inclus** : Les fichiers inclus via `include` ou `require` ne sont pas analysés
2. **Classes anonymes** : Les classes anonymes sont ignorées
3. **Trait** : Les traits ne sont pas détectés comme des directives
4. **Interfaces** : Les interfaces ne sont pas détectées

### Bonnes pratiques

1. **Limiter la profondeur** : Utilisez une profondeur raisonnable (3 par défaut)
2. **Utiliser le cache** : Mettez en cache les résultats pour les performances
3. **Fichiers séparés** : Une directive par fichier pour un scan optimal
4. **Namespace explicite** : Toujours déclarer un namespace

```php
// ✅ Bonne pratique
namespace App\Directives;

class UserDirective extends AbstractDirective
{
    // ...
}

// ❌ Mauvaise pratique (pas de namespace, sera ignoré)
class UserDirective extends AbstractDirective
{
    // ...
}
---