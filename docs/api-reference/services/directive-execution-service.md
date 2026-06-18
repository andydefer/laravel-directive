# DirectiveExecutionService - Référence Technique

## Description

Service central responsable de l'exécution complète des directives CLI. Orchestre la découverte, la recherche, le parsing, l'hydratation et l'exécution des directives. Gère également le **système de composition (Call System)** en exécutant récursivement les appels enregistrés par les directives.

## Hiérarchie

```
DirectiveExecutionService (final)
    ├── Dépend de : DirectiveDiscoveryService
    ├── Dépend de : DirectiveParserService
    ├── Dépend de : DirectiveHydratorService
    └── Dépend de : DirectiveRendererService
```

## Rôle principal

Exécuter une directive à partir d'un enregistrement d'exécution. Gère les commandes globales (`--help`, `--list`, `--version`), trouve la directive cible par signature, alias ou nom de base, parse les arguments, hydrate l'instance, exécute la directive et traite récursivement les appels enregistrés.

## Installation

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `execute(DirectiveExecutionRecord $record): ExitCode`

Exécute une directive à partir de l'enregistrement d'exécution. Cette méthode est récursive : elle exécute également tous les appels enregistrés par la directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `DirectiveExecutionRecord` | Enregistrement contenant la signature et les arguments |

**Retourne :** `ExitCode` - Code de sortie indiquant le succès ou l'échec

**Exceptions :** Aucune (toutes les exceptions sont capturées et traduites en codes de sortie)

**Exemple :**
```php
$arguments = new StringTypedCollection();
$arguments->add('John', '--role=admin');

$record = new DirectiveExecutionRecord(
    signature: 'user-create',
    arguments: $arguments
);

$exitCode = $service->execute($record);
```

## Cas d'utilisation

### Cas 1 : Exécution d'une directive simple

```php
$record = new DirectiveExecutionRecord(
    signature: 'user-list',
    arguments: new StringTypedCollection()
);

$exitCode = $service->execute($record);
// Retourne ExitCode::SUCCESS (0)
```

### Cas 2 : Exécution avec arguments et options

```php
$arguments = new StringTypedCollection();
$arguments->add('John Doe', 'john@example.com', '--role=admin');

$record = new DirectiveExecutionRecord(
    signature: 'user-create',
    arguments: $arguments
);

$exitCode = $service->execute($record);
```

### Cas 3 : Exécution via alias

```php
$record = new DirectiveExecutionRecord(
    signature: 'users',  // Alias de 'user-list'
    arguments: new StringTypedCollection()
);

$exitCode = $service->execute($record);
```

### Cas 4 : Exécution d'une directive orchestratrice (Call System)

```php
$arguments = new StringTypedCollection();
$arguments->add('123');

$record = new DirectiveExecutionRecord(
    signature: 'user-orchestrate',
    arguments: $arguments
);

// La directive parente s'exécute, puis les appels sont exécutés récursivement
$exitCode = $service->execute($record);
```

## Flux d'exécution avec Call System

```
1. Appel de execute($record)
   ↓
2. Vérification des commandes globales (--help, --list, --version)
   ↓
3. Découverte et recherche de la directive
   ↓
4. Parsing des arguments
   ↓
5. Hydratation de la directive
   ↓
6. Exécution de la directive parente
   ├── execute() → exécute la logique
   ├── Enregistrement des appels via call()
   └── Retour du résultat
   ↓
7. Récupération des appels via getCalls()
   ↓
8. Exécution récursive de chaque appel
   ├── Pour chaque call : execute($call)
   │   ├── Recherche de la directive enfant
   │   ├── Parsing des arguments
   │   ├── Hydratation
   │   ├── Exécution de l'enfant
   │   └── Traitement des appels de l'enfant
   └── Fin de la boucle
   ↓
9. Rendu du résultat (succès/échec)
   ↓
10. Retour du code de sortie final
```

## Gestion des erreurs

| Situation | Exception | Code de sortie |
|-----------|-----------|----------------|
| Directive non trouvée | - | `ExitCode::NOT_FOUND` (3) |
| Arguments invalides | `InvalidArgumentException` | `ExitCode::INVALID_ARGUMENT` (4) |
| Signature invalide | `InvalidArgumentException` | `ExitCode::INVALID_ARGUMENT` (4) |
| Erreur générale | `\Throwable` | `ExitCode::FAILURE` (1) |
| Échec de la directive | - | `ExitCode::FAILURE` (1) |
| Appel vers directive inexistante | Ignoré (pas de rupture) | Continue l'exécution |

## Intégration

`DirectiveExecutionService` s'intègre avec :

- **`DirectiveDiscoveryService`** : Découverte des directives disponibles
- **`DirectiveParserService`** : Parsing des arguments
- **`DirectiveHydratorService`** : Hydratation des instances
- **`DirectiveRendererService`** : Rendu des messages
- **`DirectiveExecutionRecord`** : Enregistrement d'entrée
- **`AbstractDirective`** : Récupération des appels via `getCalls()`

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Découverte | Une fois par exécution (cachée) |
| Recherche de directive | O(n) avec n = nombre de directives |
| Parsing | O(m) avec m = nombre d'arguments |
| Hydratation | O(k) avec k = arguments + options |
| Exécution des appels | Récursive, dépend du nombre de calls |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |
| Laravel 10+ | ✅ Optionnel |

## Exemple complet avec Call System

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

// 1. Créer les dépendances
$discovery = new DirectiveDiscoveryService($config, $hydrator);
$parser = new DirectiveParserService();
$hydrator = new DirectiveHydratorService($factory);
$renderer = new DirectiveRendererService($renderDispatcher);

// 2. Créer le service
$service = new DirectiveExecutionService(
    discovery: $discovery,
    parser: $parser,
    hydrator: $hydrator,
    renderer: $renderer,
);

// 3. Exécuter une directive orchestratrice
$arguments = new StringTypedCollection();
$arguments->add('123');

$record = new DirectiveExecutionRecord(
    signature: 'user-orchestrate',
    arguments: $arguments,
);

// 4. Exécution
// La directive parente s'exécute, puis tous ses appels sont exécutés récursivement
$exitCode = $service->execute($record);

// 5. Vérifier le résultat
if ($exitCode === ExitCode::SUCCESS) {
    echo "Orchestration completed successfully\n";
} else {
    echo "Orchestration failed with code: " . $exitCode->value . "\n";
}
```

## Récursivité des appels

`DirectiveExecutionService` gère la récursivité des appels de manière automatique :

```php
// Dans executeDirective()
private function executeDirective(DirectiveMetadataRecord $metadata, DirectiveExecutionRecord $record): ExitCode
{
    // 1. Parser et hydrater la directive
    $parsed = $this->parser->parse($metadata->signature, $record->arguments);
    $directive = $this->hydrator->hydrate($metadata->class, $parsed);

    // 2. Exécuter la directive parente
    $result = $directive->run();

    // 3. Récupérer et exécuter les appels enregistrés
    $calls = $directive->getCalls();
    foreach ($calls as $call) {
        $this->execute($call); // ← Appel récursif
    }

    // 4. Rendre le résultat
    if ($result === ExitCode::SUCCESS) {
        $this->renderer->renderSuccess('Directive executed successfully');
    } else {
        $this->renderer->renderError('Directive execution failed');
    }

    return $result;
}
```
---