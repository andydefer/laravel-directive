# DirectiveHydratorService - Référence Technique

## Description

Service responsable de l'hydratation des instances de directives avec les données parsées (arguments, options) et l'injection des dépendances (bootstrapper Laravel, interaction service).

## Hiérarchie

```
DirectiveHydratorService
    ├── Dépend de : LaravelBootstrapperContext
    └── Dépend de : DirectiveInteractionService (optionnel)
```

## Rôle principal

Transformer un enregistrement parsé (`ParsedDirectiveRecord`) en une instance de directive entièrement configurée. Gère l'injection du bootstrapper Laravel, la conversion des options (boolean normalisation), et la création d'instances pour l'extraction des métadonnées.

## Installation

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `__construct(LaravelBootstrapperContext $laravelBootstrapperContext, ?DirectiveInteractionService $interaction = null): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$laravelBootstrapperContext` | `LaravelBootstrapperContext` | Contexte du bootstrapper Laravel |
| `$interaction` | `DirectiveInteractionService|null` | Service d'interaction (créé automatiquement si null) |

Constructeur du service d'hydratation.

### `hydrate(string $class, ParsedDirectiveRecord $parsed): DirectiveInterface`

Hydrate complètement une directive avec les arguments et options parsés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `string` | FQCN de la directive (ex: `UserListDirective::class`) |
| `$parsed` | `ParsedDirectiveRecord` | Enregistrement contenant les arguments et options parsés |

**Retourne :** `DirectiveInterface` - Instance de directive hydratée

**Exemple :**
```php
$parsed = new ParsedDirectiveRecord($arguments, $options, $variadic);
$directive = $hydrator->hydrate(UserListDirective::class, $parsed);
$directive->execute();
```

### `hydrateBlueprint(string $class): DirectiveBlueprintRecord`

Extrait le blueprint d'une directive sans exécuter son constructeur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `string` | FQCN de la directive |

**Retourne :** `DirectiveBlueprintRecord` - Blueprint contenant classe, signature et description

**Exemple :**
```php
$blueprint = $hydrator->hydrateBlueprint(UserListDirective::class);
echo $blueprint->signature; // 'user-list'
echo $blueprint->description; // 'List all users'
```

### `hydrateForAliases(string $class): DirectiveInterface`

Extrait une instance de directive pour la résolution d'alias (constructeur exécuté avec contexte minimal).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `string` | FQCN de la directive |

**Retourne :** `DirectiveInterface` - Instance de directive avec contexte minimal

**Exemple :**
```php
$directive = $hydrator->hydrateForAliases(UserListDirective::class);
$aliases = $directive->getAliases(); // ['users', 'list-users']
```

### `normalizeOptions(ParsedOptionCollection $options): ParameterCollection` (privée)

Normalise les options parsées en convertissant les chaînes `'true'` et `'false'` en booléens.

## Cas d'utilisation

### Cas 1 : Hydratation complète d'une directive

```php
// Parser les arguments de la ligne de commande
$parser = new DirectiveParserService();
$argv = new StringTypedCollection();
$argv->add('John', '--role=admin');

$parsed = $parser->parse('user:create {name} {--role=}', $argv);

// Hydrater la directive
$hydrator = new DirectiveHydratorService($laravelBootstrapperContext);
$directive = $hydrator->hydrate(UserCreateDirective::class, $parsed);

// Exécuter
$exitCode = $directive->execute();
```

### Cas 2 : Extraction du blueprint pour l'affichage de l'aide

```php
$blueprint = $hydrator->hydrateBlueprint(UserListDirective::class);
echo "Signature: " . $blueprint->signature . "\n";
echo "Description: " . $blueprint->description . "\n";
```

### Cas 3 : Extraction des alias pour la résolution

```php
$directive = $hydrator->hydrateForAliases(UserListDirective::class);
$aliases = $directive->getAliases();

foreach ($aliases as $alias) {
    $this->directiveRegistry->registerAlias($alias, $directive);
}
```

### Cas 4 : Normalisation des options booléennes

```php
// Les options avec valeur 'true' ou 'false' sont automatiquement converties
$options = new ParsedOptionCollection;
$options->addOption('active', 'true', true);  // 'true' → true
$options->addOption('debug', 'false', true);  // 'false' → false

$parsed = new ParsedDirectiveRecord($arguments, $options, $variadic);
$directive = $hydrator->hydrate(TestDirective::class, $parsed);

$directive->option('active'); // true (bool)
$directive->option('debug');  // false (bool)
```

## Flux d'exécution

```
hydrate(class, parsed)
    │
    ├── 1. Création d'une instance temporaire
    │       └── ReflectionClass::newInstance(tempContext, interaction)
    │
    ├── 2. Extraction des métadonnées
    │       ├── getBlueprint()
    │       ├── getAliases()
    │       └── shouldBootLaravel()
    │
    ├── 3. Création du vrai contexte
    │       └── new DirectiveContext(blueprint, aliases, shouldBootLaravel)
    │
    ├── 4. Injection des données parsées
    │       ├── setArguments()
    │       ├── setOptions() (avec normalisation booléenne)
    │       └── setVariadicArguments()
    │
    └── 5. Création de l'instance finale
            └── ReflectionClass::newInstance(context, interaction)
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Classe non trouvée | Exception `ReflectionException` propagée |
| Constructeur sans paramètres attendus | Exception `ReflectionException` propagée |
| Options avec `'true'` | Converties en `true` (bool) |
| Options avec `'false'` | Converties en `false` (bool) |
| Autres valeurs d'options | Conservées comme `string` |

## Intégration

`DirectiveHydratorService` s'intègre avec :

- **`LaravelBootstrapperContext`** : Contexte du bootstrapper Laravel injecté dans toutes les directives
- **`DirectiveInteractionService`** : Service d'interaction pour les sorties utilisateur
- **`ParsedDirectiveRecord`** : Données parsées (arguments, options, variadic)
- **`ParameterCollection`** : Conversion des arguments en collection typée
- **`ParsedOptionCollection`** : Conversion des options avec normalisation booléenne
- **`StringTypedCollection`** : Arguments variadiques

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Hydratation complète | O(n) avec n = nombre d'arguments + options |
| Extraction blueprint | O(1) + réflexion (création d'instance temporaire) |
| Extraction alias | O(1) + réflexion (création d'instance temporaire) |
| Normalisation options | O(m) avec m = nombre d'options |
| Réflexion | Utilisée pour la création d'instances temporaires et finales |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis (réflexion, types union) |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

// 1. Créer les dépendances
$laravelBootstrapperContext = new LaravelBootstrapperContext();
$interaction = new DirectiveInteractionService(
    new RenderDispatcher(),
    new InputDispatcher()
);

// 2. Créer l'hydrateur
$hydrator = new DirectiveHydratorService(
    laravelBootstrapperContext: $laravelBootstrapperContext,
    interaction: $interaction
);

// 3. Créer un record parsé avec arguments
$arguments = new ParsedArgumentCollection();
$arguments->addArgument('John Doe', 'name');
$arguments->addArgument('john@example.com', 'email');

// 4. Ajouter des options
$options = new ParsedOptionCollection();
$options->addOption('role', 'admin', false);
$options->addOption('active', 'true', true);
$options->addOption('verbose', 'false', true);

// 5. Ajouter des arguments variadiques
$variadic = new StringTypedCollection();
$variadic->add('file1.txt');
$variadic->add('file2.txt');
$variadic->add('file3.txt');

$parsed = new ParsedDirectiveRecord($arguments, $options, $variadic);

// 6. Hydrater la directive
$directive = $hydrator->hydrate(UserCreateDirective::class, $parsed);

// 7. Accéder aux valeurs
echo $directive->argument('name');   // 'John Doe'
echo $directive->argument('email');  // 'john@example.com'
echo $directive->option('role');     // 'admin'
echo $directive->option('active');   // true (bool)
echo $directive->option('verbose');  // false (bool)

foreach ($directive->getVariadicArguments() as $file) {
    echo "Processing: {$file}\n";
}

// 8. Extraire le blueprint
$blueprint = $hydrator->hydrateBlueprint(UserListDirective::class);
echo "Signature: " . $blueprint->signature . "\n";
echo "Description: " . $blueprint->description . "\n";

// 9. Extraire les alias
$aliasDirective = $hydrator->hydrateForAliases(UserListDirective::class);
foreach ($aliasDirective->getAliases() as $alias) {
    echo "Alias: {$alias}\n";
}
```

## Voir aussi

- [`DirectiveParserService`](directive-parser-service.md) - Service de parsing des signatures
- [`DirectiveContext`](../contexts/directive-context.md) - Contexte de la directive
- [`LaravelBootstrapperContext`](../contexts/laravel-bootstrapper-context.md) - Contexte du bootstrapper Laravel
- [`DirectiveInteractionService`](directive-interaction-service.md) - Service d'interaction utilisateur
- [`ParsedDirectiveRecord`](../records/parsed-directive-record.md) - Record des données parsées
---