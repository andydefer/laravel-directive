# BuiltInDirectiveDiscovery - Référence Technique

## Description

Source de découverte pour les directives intégrées au package. Elle fournit les trois directives de base qui sont disponibles par défaut dans Laravel Directive.

## Hiérarchie / Implémentations

```
DiscoverySourceInterface
    └── BuiltInDirectiveDiscovery (final)
```

## Rôle principal

Fournir un mécanisme d'auto-découverte pour les directives natives du package. Cette classe garantit que les directives `list`, `help` et `version` sont toujours disponibles, même si l'application ne définit aucune directive personnalisée.

## Directives intégrées

| Directive | Description | Aliases |
|-----------|-------------|---------|
| `ListDirective` | Liste toutes les directives disponibles | `ls`, `-l`, `--list` |
| `HelpDirective` | Affiche l'aide et les options globales | `-h`, `--help` |
| `VersionDirective` | Affiche les informations de version | `-v`, `--version` |

## API / Méthodes publiques

### `discover(): array`

Retourne la liste des classes des directives intégrées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, class-string>` - Liste des noms de classes qualifiés

**Exceptions :** Aucune

**Exemple :**
```php
<?php

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;

$discovery = new BuiltInDirectiveDiscovery();
$directives = $discovery->discover();

// $directives = [
//     'AndyDefer\Directive\BuiltIn\ListDirective',
//     'AndyDefer\Directive\BuiltIn\HelpDirective',
//     'AndyDefer\Directive\BuiltIn\VersionDirective',
// ]
```

## Cas d'utilisation

### Cas 1 : Découverte des directives intégrées via le service provider

```php
<?php

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;

// Dans un service provider Laravel
$this->app->singleton(BuiltInDirectiveDiscovery::class);

// Le service de découverte utilisera automatiquement cette source
$discovery = $this->app->make(DirectiveDiscoveryService::class);
$directives = $discovery->discover(); // Inclut les directives intégrées
```

### Cas 2 : Extension avec des directives intégrées supplémentaires

```php
<?php

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;

// Dans un service provider, vous pouvez ajouter vos propres directives
// mais il est recommandé d'utiliser WorkspaceDirectiveDiscovery ou
// de créer votre propre DiscoverySourceInterface

class CustomBuiltInDirectiveDiscovery extends BuiltInDirectiveDiscovery
{
    private array $builtInDirectives = [
        // Conserver les directives intégrées
        ListDirective::class,
        HelpDirective::class,
        VersionDirective::class,
        // Ajouter vos directives personnalisées
        MyCustomDirective::class,
    ];
}
```

### Cas 3 : Vérification manuelle des directives disponibles

```php
<?php

$discovery = new BuiltInDirectiveDiscovery();
$directives = $discovery->discover();

foreach ($directives as $fqcn) {
    if (is_subclass_of($fqcn, AbstractDirective::class)) {
        $instance = new $fqcn();
        echo "Directive: " . $instance->getSignature() . PHP_EOL;
        echo "Description: " . $instance->getDescription() . PHP_EOL;
    }
}
```

## Flux d'exécution

```
BuiltInDirectiveDiscovery::discover()
    │
    └── return $this->builtInDirectives
        │
        ├── ListDirective::class
        ├── HelpDirective::class
        └── VersionDirective::class
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune | - | - |

Cette classe ne lève aucune exception car elle travaille avec des valeurs statiques et connues.

## Intégration

La classe `BuiltInDirectiveDiscovery` s'intègre avec :

- **`DirectiveDiscoveryService`** : Utilisée comme l'une des sources de découverte
- **`DirectiveServiceProvider`** : Enregistrée dans le conteneur
- **`AbstractDirective`** : Les classes retournées étendent cette classe abstraite

### Ordre de découverte

Les directives intégrées sont découvertes en **premier** dans le processus :

1. ✅ **BuiltInDirectiveDiscovery** (forcé, prioritaire)
2. `WorkspaceDirectiveDiscovery` (projet)
3. `VendorDirectiveDiscovery` (packages)
4. `CustomSources` (personnalisées)

Les directives intégrées sont marquées comme `force = true` dans le service de découverte, ce qui signifie qu'elles ne peuvent pas être bloquées par des signatures réservées.

## Performance

- **Complexité** : O(1) - retourne un tableau statique
- **Mémoire** : ~200 bytes (3 entrées)
- **Cache** : Aucun nécessaire
- **Optimisation** : Aucune opération lourde, simple retour de tableau

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| Laravel 9.x | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\AbstractDirective;

// Créer l'instance
$discovery = new BuiltInDirectiveDiscovery();

// Découvrir les directives intégrées
$directives = $discovery->discover();

// Analyser chaque directive
foreach ($directives as $fqcn) {
    echo "Classe: " . $fqcn . PHP_EOL;
    
    // Vérifier que c'est bien une directive valide
    if (!is_subclass_of($fqcn, AbstractDirective::class)) {
        echo "⚠️ " . $fqcn . " n'est pas une directive valide" . PHP_EOL;
        continue;
    }
    
    // Créer une instance sans exécuter le constructeur
    $reflection = new ReflectionClass($fqcn);
    $instance = $reflection->newInstanceWithoutConstructor();
    
    echo "  Signature: " . $instance->getSignature() . PHP_EOL;
    echo "  Description: " . $instance->getDescription() . PHP_EOL;
    
    $aliases = $instance->getAliases()->toArray();
    if (!empty($aliases)) {
        echo "  Aliases: " . implode(', ', $aliases) . PHP_EOL;
    }
    echo PHP_EOL;
}
```

## Notes supplémentaires

### Pourquoi cette classe existe ?

Cette classe permet d'isoler la liste des directives intégrées dans un composant dédié, ce qui facilite :

1. **Tests** : Facilement mockable
2. **Maintenance** : Une seule source de vérité pour les directives par défaut
3. **Extensibilité** : Peut être remplacée par une implémentation personnalisée

### Quand l'utiliser ?

- Directement : Pour obtenir la liste des directives natives
- Indirectement : Via `DirectiveDiscoveryService` qui l'utilise automatiquement
- En test : Pour vérifier les directives disponibles par défaut

### Différence avec les autres sources

| Source | Type | Priorité |
|--------|------|----------|
| `BuiltInDirectiveDiscovery` | Intégrées | 1 (la plus haute) |
| `WorkspaceDirectiveDiscovery` | Projet | 2 |
| `VendorDirectiveDiscovery` | Packages | 3 |
| `CustomSources` | Personnalisées | 4 |

Les directives intégrées ne peuvent pas être écrasées ou ignorées par des directives du même nom.
---