# DirectiveKernel - Référence Technique

## Description

`DirectiveKernel` est le noyau d'exécution du système de directives. Il orchestre la découverte, la résolution, l'exécution et la journalisation des directives. Il gère également le contexte partagé entre les directives, fournit des suggestions de commandes via un arbre BK-Tree, et offre des modes verbose pour le débogage.

## Hiérarchie / Implémentations

```
DirectiveDiscoveryService
    └── DirectiveKernel
```

**Classe parente :** `DirectiveDiscoveryService` (hérite des capacités de découverte des directives)

## Rôle principal

`DirectiveKernel` agit comme le point d'entrée principal pour l'exécution des directives. Il assure :

- La **découverte** des directives disponibles via le système de découverte
- L'**indexation** des commandes et alias dans un BK-Tree pour les suggestions
- L'**exécution** des directives avec parsing des arguments
- La **journalisation** des exécutions via `ExecutionStatsLogger`
- La **gestion du contexte** partagé entre directives
- La **résolution des problèmes** et le mode verbose
- La **détection de circularité** dans les appels internes
- La **suggestion** de commandes proches en cas d'erreur

## Installation

```bash
composer require andydefer/laravel-directive
```

### Dépendances

- PHP 8.1+
- `Application` - Conteneur Laravel
- `DirectiveDiscoveryService` - Découverte des directives
- `ExecutionStatsLogger` - Journalisation des statistiques
- `BKTree` - Arbre BK pour les suggestions
- `Console` - Composant de sortie console

---

## API / Méthodes publiques

### `init(Application $container): self`

Crée une nouvelle instance du noyau.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$container` | `Application` | Instance de l'application Laravel |

**Retourne :** `self` - Instance du noyau

**Exemple :**
```php
$kernel = DirectiveKernel::init($app);
```

---

### `getApplication(): Application`

Retourne l'instance de l'application.

**Retourne :** `Application` - Instance de l'application

**Exemple :**
```php
$app = $kernel->getApplication();
$logger = $app->make(Logger::class);
```

---

### `getContext(): MapCollection`

Retourne le contexte partagé entre les directives.

**Retourne :** `MapCollection` - Collection du contexte

**Exemple :**
```php
$context = $kernel->getContext();
$context->put('user', 'John');
$name = $context->get('user'); // 'John'
```

---

### `setContext(MapCollection $context): self`

Définit le contexte partagé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$context` | `MapCollection` | Nouveau contexte |

**Retourne :** `self` - Instance du noyau (fluent)

---

### `resetContext(): self`

Réinitialise le contexte partagé.

**Retourne :** `self` - Instance du noyau (fluent)

**Exemple :**
```php
$kernel->resetContext();
// Le contexte est maintenant vide
```

---

### `getLastStats(): ?ExecutionStatsRecord`

Retourne les statistiques de la dernière exécution.

**Retourne :** `?ExecutionStatsRecord` - Statistiques ou `null`

**Exemple :**
```php
$stats = $kernel->getLastStats();
if ($stats) {
    echo $stats->duration; // 0.123 seconds
}
```

---

### `getLogger(): ExecutionStatsLogger`

Retourne le logger des statistiques d'exécution.

**Retourne :** `ExecutionStatsLogger` - Instance du logger

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
| `$path` | `string` | Chemin absolu du dossier de logs |

**Retourne :** `self` - Instance du noyau (fluent)

**Exemple :**
```php
$kernel->setLogBasePath('/var/log/directive');
```

---

### `run(array $argv): ExitCode`

Exécute une directive à partir des arguments en ligne de commande.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$argv` | `array<int, string>` | Arguments de la ligne de commande |

**Retourne :** `ExitCode` - Code de sortie

**Exemple :**
```php
$exitCode = $kernel->run(['directive', 'greet', 'John', '--force']);
```

---

### `runDirective(string $fqcn, array $argv = []): ExitCode`

Exécute une directive directement par son FQCN.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fqcn` | `string` | Nom complet de la classe de la directive |
| `$argv` | `array<int, string>` | Arguments supplémentaires |

**Retourne :** `ExitCode` - Code de sortie

**Exemple :**
```php
$exitCode = $kernel->runDirective(
    'App\Directives\GreetDirective',
    ['John', '--force']
);
```

---

### `runSignature(string $query): ExitCode`

Exécute une directive à partir d'une signature complète.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Signature complète avec arguments |

**Retourne :** `ExitCode` - Code de sortie

**Exemple :**
```php
$exitCode = $kernel->runSignature('greet John --force');
```

---

### `verbose(bool $enabled = true): self`

Active ou désactive le mode verbose.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$enabled` | `bool` | `true` pour activer, `false` pour désactiver |

**Retourne :** `self` - Instance du noyau (fluent)

**Exemple :**
```php
$kernel->verbose(true);
// Les problèmes seront affichés après l'exécution
```

---

### `withOutput(): self`

Active la sortie standard (désactive le mode verbose).

**Retourne :** `self` - Instance du noyau (fluent)

---

### `withoutOutput(): self`

Désactive la sortie standard (active le mode verbose).

**Retourne :** `self` - Instance du noyau (fluent)

---

### `isVerbose(): bool`

Vérifie si le mode verbose est activé.

**Retourne :** `bool` - `true` si le verbose est activé, `false` sinon

---

## Cas d'utilisation

### Cas 1 : Exécution de base

```php
<?php

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;

$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class
])->build();

$kernel = DirectiveKernel::init($app);
$kernel->addSource('/path/to/custom/directives');

$exitCode = $kernel->run(['directive', 'greet', 'John']);
exit($exitCode->value);
```

---

### Cas 2 : Exécution avec contexte partagé

```php
<?php

use AndyDefer\Directive\DirectiveKernel;

// Première directive : initialise le contexte
$kernel->run(['directive', 'context:set', 'user_id', '123']);

// Deuxième directive : utilise le contexte
$kernel->run(['directive', 'process:user']);

// Récupération du contexte après exécution
$context = $kernel->getContext();
$userId = $context->get('user_id'); // '123'
```

---

### Cas 3 : Exécution avec mode verbose

```php
<?php

use AndyDefer\Directive\DirectiveKernel;

$kernel = DirectiveKernel::init($app);

// Activer le mode verbose
$kernel->verbose(true)
    ->addSource('/path/to/directives');

// Les problèmes seront affichés après l'exécution
$exitCode = $kernel->run(['directive', 'unknown-command']);

// Output des problèmes en JSON
// === 1 Problem(s) Encountered ===
// {
//   "key": "directive_not_found",
//   "context": "Directive not found: unknown-command",
//   "message": "No directive matching the command name was found",
//   "timestamp": "2024-01-01 12:00:00",
//   "context_data": {
//     "command": "unknown-command",
//     "query": "unknown-command"
//   }
// }
```

---

### Cas 4 : Suggestions de commandes

```php
<?php

use AndyDefer\Directive\DirectiveKernel;

$kernel = DirectiveKernel::init($app);
$kernel->addSource('/path/to/directives');

// Exécution d'une commande inexistante
$kernel->run(['directive', 'gret', 'John']);

// Output:
// Directive not found: gret
// 💡 Did you mean:
//   • greet
```

---

### Cas 5 : Exécution programmatique

```php
<?php

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;

$kernel = DirectiveKernel::init($app);

// Exécution par FQCN
$exitCode = $kernel->runDirective(
    'AndyDefer\Directive\BuiltIn\VersionDirective',
    ['--verbose']
);

// Exécution par signature
$exitCode = $kernel->runSignature('greet John --force');

// Vérification du résultat
if ($exitCode === ExitCode::SUCCESS) {
    echo "✅ Success!";
} else {
    $stats = $kernel->getLastStats();
    echo "❌ Failed with code: " . $exitCode->value;
}
```

---

### Cas 6 : Gestion du contexte avancé

```php
<?php

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\DomainStructures\Utils\MapCollection;

$kernel = DirectiveKernel::init($app);

// Initialisation du contexte
$context = new MapCollection([
    'user' => 'John Doe',
    'role' => 'admin',
    'session_id' => 'abc123',
]);
$kernel->setContext($context);

// Exécution d'une directive qui modifie le contexte
$kernel->run(['directive', 'process:session']);

// Sauvegarde du contexte pour usage ultérieur
$snapshot = $kernel->getContext();

// ... autre traitement ...

// Restauration du contexte
$kernel->setContext($snapshot);
```

---

## Flux d'exécution

```
run($argv)
    ↓
isMissingCommand($argv) ?
    ├── Oui → executeHelpDirective()
    └── Non ↓
parseArguments($argv)
    ├── commandName = $argv[1]
    └── query = implode(' ', array_slice($argv, 1))
    ↓
executeDirective($commandName, $query)
    ↓
getDirectives()
    ├── Si cache vide → discover() → initializeBKTree()
    └── Retourne DirectiveMetadataCollection
    ↓
findDirective($directives, $commandName)
    ├── matchesCommandName() (signature)
    ├── matchesAlias() (alias)
    └── Si non trouvé →
        ├── Afficher erreur
        ├── getSuggestions($commandName)
        ├── Afficher suggestions
        ├── addProblem('directive_not_found')
        └── Retourne NOT_FOUND
    ↓
instantiateAndRun($directive, $query)
    ↓
startTime = microtime(true)
startMemory = memory_get_usage()
    ↓
$instance = new $directive->class($this, $query)
    ↓
$exitCode = $instance->run()
    ↓
logExecution($directive, $commandName, $exitCode)
    ├── $duration = microtime(true) - startTime
    ├── $memoryUsed = memory_get_usage() - startMemory
    ├── $record = new ExecutionStatsRecord(...)
    ├── lastStats = $record
    └── logger->log($record, $context)
    ↓
verbose ?
    ├── Oui → displayProblems()
    └── Non → (skip)
    ↓
Retourne ExitCode
```

---

## Gestion des erreurs

| Situation | Problème | Contexte | Message |
|-----------|----------|----------|---------|
| Directive non trouvée | `directive_not_found` | `Directive not found: {command}` | `No directive matching the command name was found` |
| Échec d'exécution | `run_execution` | `Failed to execute command` | Message de l'exception |
| Échec d'instanciation | `instantiate_and_run` | `Failed to instantiate and run directive: {class}` | Message de l'exception |
| Échec de découverte | `get_directives` | `Failed to get directives from cache` | Message de l'exception |
| Échec du logger | `logger_resolution` | `Failed to resolve ExecutionStatsLogger from container` | Message de l'exception |
| Échec BK-Tree | `bk_tree_initialization` | `Failed to initialize BKTree for directive suggestions` | Message de l'exception |
| Échec suggestions | `get_suggestions` | `Failed to get suggestions for command: {command}` | Message de l'exception |
| Échec indexation | `index_directive` | `Failed to index directive in BKTree: {signature}` | Message de l'exception |
| Échec log | `log_execution` | `Failed to log execution for directive: {command}` | Message de l'exception |
| Échec set log path | `set_log_base_path` | `Failed to set log base path: {path}` | Message de l'exception |

**Codes de sortie associés :**
- `ExitCode::NOT_FOUND` (3) - Directive non trouvée
- `ExitCode::RUNTIME_ERROR` (5) - Erreur d'exécution
- `ExitCode::CONFLICT` (4) - Circularité détectée
- `ExitCode::SUCCESS` (0) - Succès

---

## Performance

- **Découverte** : Effectuée une fois, mise en cache
- **BK-Tree** : Indexation O(n log n), recherche O(log n) avec distance de Levenshtein
- **Exécution** : Parsing O(n), instanciation via Reflection (mise en cache)
- **Journalisation** : Écriture asynchrone en JSONL (append)
- **Mémoire** : Minimale, les collections sont immuables

### Optimisations

- Cache des directives découvertes
- BK-Tree initialisé une seule fois
- Réflexion avec cache des classes
- Écriture des logs en append (pas de réécriture)

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.4 | ✅ Complet |
| PHP 8.3 | ✅ Complet |
| PHP 8.2 | ✅ Complet |
| PHP 8.1 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Enums\ExitCode;

// 1. Création de l'application
$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class
])->build();

// 2. Initialisation du noyau
$kernel = DirectiveKernel::init($app);

// 3. Configuration
$kernel->addSource('/custom/directives')
    ->setLogBasePath('/var/log/directive')
    ->verbose(true);

// 4. Exécution avec gestion des erreurs
try {
    $exitCode = $kernel->run($argv);
    
    // 5. Vérification du résultat
    if ($exitCode === ExitCode::SUCCESS) {
        echo "✅ Command executed successfully\n";
        
        // Afficher les statistiques
        $stats = $kernel->getLastStats();
        if ($stats) {
            echo sprintf(
                "Duration: %.4fs | Memory: %d bytes\n",
                $stats->duration,
                $stats->memoryUsage
            );
        }
    } else {
        echo "❌ Command failed with code: " . $exitCode->value . "\n";
        
        // Afficher les problèmes
        $problems = $kernel->getProblems();
        foreach ($problems as $problem) {
            echo "Problem: " . $problem->get('context') . "\n";
        }
    }
    
    exit($exitCode->value);
} catch (Throwable $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(255);
}
```

---

## Voir aussi

- `DirectiveDiscoveryService` - Service de découverte des directives
- `ExecutionStatsLogger` - Journalisation des statistiques
- `AbstractDirective` - Classe de base des directives
- `ExitCode` - Énumération des codes de sortie
- `BKTree` - Arbre BK pour les suggestions
- `MapCollection` - Collection pour le contexte