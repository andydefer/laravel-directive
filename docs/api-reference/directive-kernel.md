# DirectiveKernel - Référence Technique

## Description

Noyau d'exécution du système de directives. Orchestre la découverte, l'instanciation et l'exécution des commandes avec un système de suggestions intelligent basé sur un arbre BK-tree.

## Hiérarchie / Implémentations

```
DirectiveDiscoveryService
    └── DirectiveKernel
```

## Rôle principal

`DirectiveKernel` est le point d'entrée central du package Directive. Il permet de :

- Découvrir automatiquement toutes les directives disponibles (hérite de `DirectiveDiscoveryService`)
- Exécuter les commandes avec leurs arguments
- Fournir des suggestions de commandes via BK-tree (distance de Levenshtein)
- Gérer le contexte partagé entre les directives
- Journaliser les statistiques d'exécution au format JSONL
- Détecter les erreurs et les enregistrer
- Mettre en cache les directives pour des performances optimales

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `Container` - Conteneur de dépendances
- `ExecutionStatsLogger` - Journalisation des statistiques
- `BKTree` - Arbre pour les suggestions de commandes
- `MemoryStorage` - Stockage en mémoire pour le BK-tree
- `Console` - Sortie console pour les messages
- PHP 8.1+

## API / Méthodes publiques

### `static init(Container $container): self`

Initialise le noyau avec un conteneur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$container` | `Container` | Conteneur de dépendances configuré |

**Retourne :** `self` - Instance du noyau

**Exemple :**
```php
$container = DirectiveContainer::create();
$kernel = DirectiveKernel::init($container);
```

---

### `run(array $argv): ExitCode`

Exécute une commande à partir des arguments en ligne de commande.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$argv` | `array<int, string>` | Arguments de la ligne de commande |

**Retourne :** `ExitCode` - Code de sortie (SUCCESS, NOT_FOUND, RUNTIME_ERROR, etc.)

**Exceptions :** Aucune

**Exemple :**
```php
$exitCode = $kernel->run(['directive', 'list']);
// Affiche la liste des directives
```

---

### `runDirective(string $fqcn, array $argv = []): ExitCode`

Exécute une directive par son nom de classe complet.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fqcn` | `class-string<AbstractDirective>` | Nom de classe complet |
| `$argv` | `array<int, string>` | Arguments (sans le nom de la directive) |

**Retourne :** `ExitCode` - Code de sortie

**Exemple :**
```php
$exitCode = $kernel->runDirective(
    'AndyDefer\\Directive\\BuiltIn\\ListDirective',
    ['--format', 'json']
);
```

---

### `runSignature(string $query): ExitCode`

Exécute une directive à partir d'une chaîne de requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Requête complète (ex: `"greet John --formal"`) |

**Retourne :** `ExitCode` - Code de sortie

**Exemple :**
```php
$exitCode = $kernel->runSignature('test-directive John john@example.com');
```

---

### `getContainer(): Container`

Retourne le conteneur de dépendances.

**Retourne :** `Container` - Instance du conteneur

---

### `getContext(): MapCollection`

Retourne le contexte partagé entre les directives.

**Retourne :** `MapCollection` - Contexte sous forme de tableau clé-valeur

**Exemple :**
```php
$context = $kernel->getContext();
$userName = $context->get('user_name', 'anonymous');
```

---

### `setContext(MapCollection $context): self`

Définit le contexte (utile pour les tests ou l'isolation).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$context` | `MapCollection` | Nouveau contexte |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$context = MapCollection::from(['user_name' => 'Alice']);
$kernel->setContext($context);
```

---

### `resetContext(): self`

Réinitialise le contexte à vide.

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$kernel->resetContext(); // Toutes les directives partent d'un contexte vide
```

---

### `getLastStats(): ?ExecutionStatsRecord`

Retourne les statistiques de la dernière exécution.

**Retourne :** `?ExecutionStatsRecord` - Statistiques ou `null` si aucune exécution

**Exemple :**
```php
$stats = $kernel->getLastStats();
if ($stats) {
    echo "Duration: " . $stats->duration . "s\n";
    echo "Memory: " . $stats->memoryUsage . " bytes\n";
}
```

---

### `getLogger(): ExecutionStatsLogger`

Retourne le service de journalisation.

**Retourne :** `ExecutionStatsLogger` - Logger configuré

**Exemple :**
```php
$logger = $kernel->getLogger();
$logger->setBasePath('/custom/log/path');
```

---

### `setLogBasePath(string $path): self`

Définit le chemin de base pour les logs.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Chemin absolu vers le dossier des logs |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$kernel->setLogBasePath('/var/log/directive');
```

---

## Cas d'utilisation

### Cas 1 : Exécution d'une commande simple

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;

$container = DirectiveContainer::create(__DIR__);
$kernel = DirectiveKernel::init($container);

// Exécuter la commande 'help'
$exitCode = $kernel->run(['directive', 'help']);

if ($exitCode === ExitCode::SUCCESS) {
    echo "Command executed successfully\n";
}
```

### Cas 2 : Exécution avec arguments et options

```php
<?php

$exitCode = $kernel->run([
    'directive',
    'test-directive',
    'John',
    'john@example.com',
    '--force',
    '--verbose'
]);

// Ou via runSignature
$exitCode = $kernel->runSignature('test-directive John john@example.com --force');
```

### Cas 3 : Utilisation du contexte partagé

```php
<?php

// Une directive définit un contexte
$kernel->runSignature('context:set John');

// Une autre directive peut le lire
$kernel->runSignature('context:get');
// Affiche : {"user_name":"John","counter":1}

// Accès programmatique
$context = $kernel->getContext();
$userName = $context->get('user_name');
echo "User: $userName\n";
```

### Cas 4 : Suggestions automatiques

```php
<?php

// Lorsqu'une commande est mal tapée
$exitCode = $kernel->run(['directive', 'lst']);

// Affiche :
// Directive not found: lst
//
// 💡 Did you mean:
//   • list
//   • help
//   • version
```

### Cas 5 : Intégration dans un script personnalisé

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;

$container = DirectiveContainer::create(__DIR__);
$kernel = DirectiveKernel::init($container);

// Ajouter des sources personnalisées
$kernel->addCustomSource('/src/Directives');

// Exécuter les arguments passés au script
$exitCode = $kernel->run($argv);
exit($exitCode->value);
```

---

## Flux d'exécution

```
run($argv)
    ↓
isMissingCommand()? → Oui → help
    ↓
parseArguments()
    ↓
executeDirective($commandName, $query)
    ↓
getDirectives() (avec cache)
    ├── Cache vide → discover()
    │   ├── Découverte de toutes les directives
    │   └── initializeBKTree()
    │       ├── Indexation des commandes
    │       └── Indexation des alias
    └── Cache présent → utilisation directe
    ↓
findDirective()
    ├── Trouvé → instantiateAndRun()
    │   ├── Début du tracking (time + memory)
    │   ├── new $directive->class($this, $query)
    │   │   ├── beforeExecute() (si présent)
    │   │   ├── execute() (la logique métier)
    │   │   └── afterExecute() (si présent)
    │   ├── Fin du tracking
    │   └── logExecution()
    └── Non trouvé → suggestions BK-tree
        ├── search($commandName, 2, 5)
        └── Afficher les suggestions
    ↓
Retourner ExitCode
```

### Détails des suggestions BK-tree

```
initializeBKTree()
    ↓
Pour chaque directive découverte
    ↓
indexDirective($directive)
    ├── Insertion du nom de commande
    └── Insertion de chaque alias
    ↓
BK-tree prêt pour les requêtes
    ↓
getSuggestions($commandName, 2, 5)
    ├── search avec distance maximale 2
    ├── Limite à 5 résultats
    └── Retourne les commandes similaires
```

---

## Gestion des erreurs

| Situation | ExitCode | Message |
|-----------|----------|---------|
| Aucune commande fournie | `SUCCESS` | Affiche l'aide (help) |
| Commande non trouvée | `NOT_FOUND` | `Directive not found: {command}` + suggestions |
| Erreur d'exécution | `RUNTIME_ERROR` | Dépend de la directive |
| Conflit (circularité) | `CONFLICT` | Dépend de la directive |
| Exception non capturée | `RUNTIME_ERROR` | Message de l'exception |

Les erreurs d'initialisation du BK-tree sont silencieusement ignorées (pas de suggestions).

---

## Intégration

### Avec DirectiveContainer

```php
// Conteneur pré-configuré
$container = DirectiveContainer::create('/path/to/project');
$kernel = DirectiveKernel::init($container);
```

### Avec Laravel (via ServiceProvider)

```php
// Dans config/app.php
'providers' => [
    AndyDefer\Directive\DirectiveServiceProvider::class,
];

// Récupération
$kernel = app(DirectiveKernel::class);
$exitCode = $kernel->run($_SERVER['argv']);
```

### Avec un conteneur personnalisé

```php
<?php

use AndyDefer\Directive\Container\Container;

class CustomContainer extends Container
{
    protected function registerStandaloneServices(): void
    {
        // Ajout de services personnalisés
        $this->singleton(MyCustomService::class, function ($c) {
            return new MyCustomService($c->make(OtherService::class));
        });
    }
}

$container = new CustomContainer('/path/to/project');
$kernel = DirectiveKernel::init($container);
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| Découverte initiale | O(n) | n = nombre de classes PHP |
| Recherche de directive | O(1) | Cache en mémoire |
| Suggestion BK-tree | O(log n) | Recherche dans l'arbre |
| Exécution d'une directive | O(1) + logique métier | Dépend de la directive |

**Optimisations :**
- Cache des directives (`$directivesCache`)
- Indexation BK-tree une seule fois
- Pas de relecture des fichiers à chaque appel
- Journalisation asynchrone (buffers JSONL)

**Mémoire :**
- Toutes les directives sont chargées en mémoire
- BK-tree indexe les noms de commandes et alias
- Le contexte partagé est conservé pendant l'exécution
- Les statistiques de la dernière exécution sont stockées

---

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.4 | ✅ Complet | Support total |
| PHP 8.3 | ✅ Complet | Support total |
| PHP 8.2 | ✅ Complet | Support total |
| PHP 8.1 | ✅ Complet | Support total |

**Dépendances requises :**
- `andydefer/console-writer` ^1.3
- `andydefer/algo-kit` ^0.8
- `andydefer/storage-kit` ^0.7
- `illuminate/contracts` ^12.0|^13.0|^14.0|^15.0

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Enums\DiscoverySource;
use AndyDefer\DomainStructures\Utils\MapCollection;

// 1. Création du conteneur
$container = DirectiveContainer::create(__DIR__);

// 2. Initialisation du noyau
$kernel = DirectiveKernel::init($container);

// 3. Configuration du contexte initial
$initialContext = MapCollection::from([
    'environment' => 'development',
    'user_id' => 42,
    'start_time' => microtime(true),
]);
$kernel->setContext($initialContext);

// 4. Configuration du chemin de logs personnalisé
$kernel->setLogBasePath('/var/log/directive');

// 5. Configuration de la découverte
$kernel
    ->addSource(__DIR__ . '/src/Directives')
    ->addSource(__DIR__ . '/app/Commands')
    ->ignoreSource(DiscoverySource::VENDOR)
    ->onlyNamespace('App\\Directives\\');

// 6. Exécution des commandes
echo "=== Test de différentes commandes ===\n\n";

// 6a. Aide
echo "--- Help ---\n";
$kernel->run(['directive', 'help']);
echo "\n";

// 6b. Liste des directives
echo "--- List ---\n";
$kernel->run(['directive', 'list']);
echo "\n";

// 6c. Exécution d'une directive avec arguments
echo "--- Test Directive ---\n";
$exitCode = $kernel->run([
    'directive',
    'test-directive',
    'John Doe',
    'john@example.com',
    '--force'
]);
echo "Exit code: " . $exitCode->value . " (" . $exitCode->getLabel() . ")\n\n";

// 6d. Test des suggestions
echo "--- Suggestions ---\n";
$kernel->run(['directive', 'lst']); // 'list' mal tapé
echo "\n";

// 6e. Test avec runSignature
echo "--- Run Signature ---\n";
$kernel->runSignature('greet Alice --formal');
echo "\n";

// 6f. Test avec runDirective
echo "--- Run Directive by FQCN ---\n";
$kernel->runDirective(
    'AndyDefer\\Directive\\BuiltIn\\VersionDirective',
    ['--verbose']
);
echo "\n";

// 7. Récupération des statistiques
$stats = $kernel->getLastStats();
if ($stats) {
    echo "=== Dernière exécution ===\n";
    echo "Commande: {$stats->command}\n";
    echo "Durée: " . round($stats->duration * 1000, 2) . " ms\n";
    echo "Mémoire: " . number_format($stats->memoryUsage / 1024, 2) . " KB\n";
    echo "Mémoire pic: " . number_format($stats->peakMemoryUsage / 1024, 2) . " KB\n";
    echo "Code de sortie: {$stats->exitCode->value} ({$stats->exitCode->getLabel()})\n";
    echo "Succès: " . ($stats->exitCode->isSuccess() ? '✅' : '❌') . "\n";
}

// 8. Récupération du contexte final
$finalContext = $kernel->getContext();
echo "\n=== Contexte final ===\n";
print_r($finalContext->toArray());

// 9. Vérification des suggestions
echo "\n=== Suggestions disponibles ===\n";
$directives = $kernel->getDirectives();
$names = [];
foreach ($directives as $directive) {
    $parts = explode(' ', $directive->signature);
    $names[] = $parts[0];
}
echo "Commandes disponibles: " . implode(', ', $names) . "\n";

// 10. Nettoyage
$kernel->resetContext();
$kernel->setLogBasePath('.directive');
echo "\n✅ Kernel réinitialisé\n";
```

## Voir aussi

- `DirectiveDiscoveryService` - Découverte des directives
- `ExecutionStatsLogger` - Journalisation des statistiques
- `DirectiveContainer` - Conteneur pré-configuré
- `AbstractDirective` - Base pour créer des directives
- `ExitCode` - Énumération des codes de sortie
- `BKTree` - Structure de données pour les suggestions