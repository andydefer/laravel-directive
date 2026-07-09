# AbstractDirective - Référence Technique

## Description

Classe abstraite de base pour toutes les directives. Elle fournit les fonctionnalités communes nécessaires à l'exécution des directives : parsing des arguments, gestion des flags, méthodes de sortie, et exécution des appels internes. Les directives sont des commandes CLI autonomes qui définissent une signature, des alias et une logique d'exécution.

## Hiérarchie / Implémentations

```
DirectiveInterface
    └── AbstractDirective (abstract)
        ├── HelpDirective
        ├── ListDirective
        └── VersionDirective
```

## Rôle principal

Servir de classe de base pour toutes les directives du package. Elle centralise :
1. Le parsing des signatures et des requêtes
2. L'accès aux arguments, flags et valeurs variadiques
3. Les méthodes de sortie (console)
4. La gestion des appels internes (chaînage de directives)
5. La détection des dépendances circulaires

## Installation

### Créer une nouvelle directive

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class MyDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'my:command {name} {--force}';
    }

    public function getDescription(): string
    {
        return 'Ma commande personnalisée';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['mc']);
    }

    protected function execute(): ExitCode
    {
        $name = $this->argument('name');
        $force = $this->flag('force');
        
        $this->info("Bonjour {$name} !");
        
        if ($force) {
            $this->line("Mode forcé activé");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

## API / Méthodes publiques

### `getLaravel(): Application`

Récupère l'instance de l'application Laravel.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `Application` - L'application Laravel

**Exemple :**
```php
<?php

$app = $this->getLaravel();
$config = $app->make(Config::class);
```

---

### `getConsole(): Console`

Récupère l'instance de la console pour les opérations de sortie.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `Console` - L'instance de la console

**Exemple :**
```php
<?php

$console = $this->getConsole();
$console->title('Mon Titre');
```

---

### `getParsed(): ParsedSignatureRecord`

Récupère le record de la signature parsée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `ParsedSignatureRecord` - Les données parsées de la signature

**Exemple :**
```php
<?php

$parsed = $this->getParsed();
$required = $parsed->required->toArray();
```

---

### `argument(string $key): mixed`

Récupère la valeur d'un argument (requis ou par défaut).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Le nom de l'argument |

**Retourne :** `mixed` - La valeur de l'argument, ou `null` si non trouvé

**Exemple :**
```php
<?php

$name = $this->argument('name');
$email = $this->argument('email') ?? 'default@example.com';
```

---

### `hasArgument(string $key): bool`

Vérifie si un argument existe (requis ou par défaut).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Le nom de l'argument |

**Retourne :** `bool` - `true` si l'argument existe, `false` sinon

**Exemple :**
```php
<?php

if ($this->hasArgument('name')) {
    $name = $this->argument('name');
}
```

---

### `flag(string $key): bool`

Récupère la valeur d'un flag.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Le nom du flag |

**Retourne :** `bool` - `true` si le flag est présent, `false` sinon

**Exemple :**
```php
<?php

if ($this->flag('force')) {
    $this->line('Mode forcé');
}
```

---

### `hasFlag(string $key): bool`

Vérifie si un flag existe dans la signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Le nom du flag |

**Retourne :** `bool` - `true` si le flag existe, `false` sinon

**Exemple :**
```php
<?php

if ($this->hasFlag('force')) {
    $force = $this->flag('force');
}
```

---

### `isFlagActive(string $key): bool`

Vérifie si un flag est actif dans la requête courante.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Le nom du flag |

**Retourne :** `bool` - `true` si le flag est actif, `false` sinon

**Exemple :**
```php
<?php

if ($this->isFlagActive('admin')) {
    // Exécuter en mode administrateur
}
```

---

### `getVariadicArguments(): StringTypedCollection`

Récupère tous les arguments variadiques.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `StringTypedCollection` - Collection des valeurs variadiques

**Exemple :**
```php
<?php

$files = $this->getVariadicArguments();
foreach ($files as $file) {
    $this->line("Fichier: {$file}");
}
```

---

### `line(string $message): void`

Affiche une ligne de texte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message à afficher |

**Exemple :**
```php
<?php

$this->line('Hello World');
```

---

### `info(string $message): void`

Affiche un message d'information (en vert).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message à afficher |

**Exemple :**
```php
<?php

$this->info('Succès !');
```

---

### `error(string $message): void`

Affiche un message d'erreur (en rouge).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message à afficher |

**Exemple :**
```php
<?php

$this->error('Une erreur est survenue');
```

---

### `table(ListCollection|array $headers, ListCollection|array $rows): void`

Affiche un tableau.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$headers` | `ListCollection|array` | Les en-têtes du tableau |
| `$rows` | `ListCollection|array` | Les lignes du tableau |

**Exemple :**
```php
<?php

$this->table(
    ['ID', 'Nom', 'Email'],
    [
        [1, 'John Doe', 'john@example.com'],
        [2, 'Jane Doe', 'jane@example.com'],
    ]
);
```

---

### `call(string $query): void`

Enfile un appel interne vers une autre directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | La requête à exécuter |

**Exemple :**
```php
<?php

$this->call('list');
$this->call('db:backup --force');
```

---

### `getCalls(): array`

Récupère la liste des appels internes.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, DirectiveCallRecord>` - Liste des appels

**Exemple :**
```php
<?php

$calls = $this->getCalls();
foreach ($calls as $call) {
    echo $call->query . PHP_EOL;
}
```

---

### `run(): ExitCode`

Exécute la directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `ExitCode` - Le code de sortie

**Exemple :**
```php
<?php

$exitCode = $this->run();
exit($exitCode->value);
```

---

### `getAliases(): StringTypedCollection`

Récupère les alias de la directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```php
<?php

public function getAliases(): StringTypedCollection
{
    return StringTypedCollection::from(['u', 'user']);
}
```

---

### `getSignature(): string`

Récupère la signature de la directive (à implémenter).

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - La signature

**Exemple :**
```php
<?php

public function getSignature(): string
{
    return 'user:create {name} {email?} {--admin}';
}
```

## Hooks

### `beforeExecute(): void`

Hook appelé avant l'exécution principale.

**Exemple :**
```php
<?php

protected function beforeExecute(): void
{
    $this->line('Début de l\'exécution...');
}
```

### `afterExecute(ExitCode $exitCode): void`

Hook appelé après l'exécution principale.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$exitCode` | `ExitCode` | Le code de sortie de l'exécution |

**Exemple :**
```php
<?php

protected function afterExecute(ExitCode $exitCode): void
{
    $this->line('Fin de l\'exécution');
}
```

## Cas d'utilisation

### Cas 1 : Directive avec arguments et flags

```php
<?php

final class BackupDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'backup {file?} {--force} {--compression=gzip} {--format=sql}';
    }
    
    protected function execute(): ExitCode
    {
        $file = $this->argument('file') ?? date('Y-m-d') . '.sql';
        $compression = $this->flag('compression');
        $format = $this->flag('format');
        $force = $this->flag('force');
        
        $this->info("Sauvegarde de {$file}");
        $this->info("Format: {$format}");
        
        if ($compression) {
            $this->info("Compression: {$compression}");
        }
        
        if ($force) {
            $this->line('Mode forcé');
        }
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 2 : Directive avec appel interne

```php
<?php

final class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {environment} {--migrate} {--seed}';
    }
    
    protected function execute(): ExitCode
    {
        $env = $this->argument('environment');
        
        $this->info("Déploiement vers {$env}");
        
        // Exécuter les directives de maintenance
        $this->call('cache:clear');
        $this->call('config:cache');
        
        if ($this->flag('migrate')) {
            $this->call('db:migrate --force');
        }
        
        if ($this->flag('seed')) {
            $this->call('db:seed');
        }
        
        $this->info("Déploiement terminé");
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 3 : Directive interactive

```php
<?php

final class UserCreateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user:create {name} {--admin}';
    }
    
    protected function execute(): ExitCode
    {
        $name = $this->argument('name');
        $isAdmin = $this->flag('admin');
        
        // Confirmation interactive
        if (!$this->confirm("Créer l'utilisateur {$name} ?")) {
            $this->line('Annulé');
            return ExitCode::SUCCESS;
        }
        
        // Demander des informations supplémentaires
        $email = $this->ask("Email pour {$name} :");
        
        $this->info("Utilisateur {$name} créé avec l'email {$email}");
        
        if ($isAdmin) {
            $this->info("Droits administrateur attribués");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

## Flux d'exécution

```
AbstractDirective::run()
    │
    ├── try
    │   ├── beforeExecute()
    │   │   └── Hook personnalisé
    │   │
    │   ├── execute()
    │   │   └── Logique métier de la directive
    │   │       ├── Accès aux arguments
    │   │       ├── Accès aux flags
    │   │       └── Appels internes ($this->call())
    │   │
    │   ├── executeCalls()
    │   │   └── foreach($calls)
    │   │       └── executeCall()
    │   │           ├── extractCommandName()
    │   │           ├── findDirective()
    │   │           ├── isCircularCall()
    │   │           └── executeDirectiveInstance()
    │   │
    │   └── afterExecute()
    │       └── Hook personnalisé
    │
    └── catch(Throwable)
        ├── Gestion des erreurs
        └── Retourne ExitCode::RUNTIME_ERROR
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Erreur dans `beforeExecute()` | `Throwable` | `Error in before hook: {message}` |
| Erreur dans `execute()` | `Throwable` | `Error in execute hook: {message}` |
| Directive non trouvée | Aucune | `Directive not found: {command}` |
| Appel circulaire détecté | Aucune | `Circular call detected: {query}` |
| Erreur d'exécution d'appel | Aucune | `Error executing call: {message}` |

### Messages d'erreur typiques

```php
// Erreur dans beforeExecute()
"Error in before hook: Undefined variable $foo"

// Erreur dans execute()
"Error in execute hook: Connection refused"

// Directive non trouvée
"Directive not found: nonexistent"

// Appel circulaire
"Circular call detected: list"

// Erreur d'exécution
"Error executing call: Invalid argument"
```

## Intégration

L'`AbstractDirective` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `DirectiveInterface` | Implémentation de l'interface |
| `DirectiveParserService` | Parsing des signatures |
| `DirectiveDiscoveryService` | Découverte des directives |
| `Console` | Sortie console |
| `Application` | Conteneur Laravel |

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Parsing | 1-5ms | Parsing de la signature |
| Exécution | Variable | Dépend de la directive |
| Mémoire | < 1MB | Minimal |

### Optimisations

```php
// Utiliser le cache pour les opérations coûteuses
protected function execute(): ExitCode
{
    static $cache = [];
    
    if (isset($cache['expensive_operation'])) {
        return $cache['expensive_operation'];
    }
    
    // Opération coûteuse
    $result = $this->expensiveOperation();
    $cache['expensive_operation'] = $result;
    
    return $result;
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | Support `readonly` |
| Laravel 9.x | ✅ Complet | - |
| Laravel 10.x | ✅ Complet | - |
| Laravel 11.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Commande de déploiement avancée.
 *
 * Exemple complet d'une directive avec toutes les fonctionnalités :
 * - Arguments requis et optionnels
 * - Flags avec valeurs
 * - Arguments variadiques
 * - Appels internes
 * - Hooks
 * - Sortie interactive
 */
final class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {environment} {--migrate} {--seed} {--compression=gzip} {files*}';
    }
    
    public function getDescription(): string
    {
        return 'Déploie l\'application dans l\'environnement spécifié';
    }
    
    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['d', 'dp']);
    }
    
    protected function beforeExecute(): void
    {
        $this->newLine();
        $this->info('=== DÉPLOIEMENT ===');
        $this->newLine();
    }
    
    protected function execute(): ExitCode
    {
        $env = $this->argument('environment');
        $files = $this->getVariadicArguments();
        
        $this->info("Environnement: {$env}");
        
        // Vérification des fichiers
        if ($files->isNotEmpty()) {
            $this->line("Fichiers à déployer:");
            foreach ($files as $file) {
                $this->line("  - {$file}");
            }
        }
        
        // Exécution des tâches de maintenance
        $this->call('cache:clear');
        $this->call('config:cache');
        
        // Migration
        if ($this->flag('migrate')) {
            $this->call('db:migrate --force');
            $this->info('✅ Migration effectuée');
        }
        
        // Seed
        if ($this->flag('seed')) {
            $this->call('db:seed');
            $this->info('✅ Seed effectué');
        }
        
        // Compression
        $compression = $this->flag('compression');
        $this->info("Compression: {$compression}");
        
        return ExitCode::SUCCESS;
    }
    
    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->newLine();
        
        if ($exitCode->isSuccess()) {
            $this->info('✅ Déploiement réussi !');
        } else {
            $this->error('❌ Déploiement échoué');
        }
        
        $this->newLine();
    }
}

// Utilisation
// php directive deploy production --migrate --seed --compression=zstd fichiers/*
```

## Notes techniques

### Détection des appels circulaires

L'AbstractDirective utilise une pile d'exécution pour détecter les appels circulaires :

```php
private static array $executionStack = [];

// Exemple de circulation
// Directive A appelle Directive B
// Directive B appelle Directive A
// → "Circular call detected: A"
```

### Gestion des hooks

Les hooks `beforeExecute()` et `afterExecute()` sont optionnels mais permettent de :
- Initialiser des ressources
- Afficher des en-têtes/pieds de page
- Effectuer des nettoyages
- Logger des informations

### Méthodes finales

Les méthodes suivantes sont `final` et ne peuvent pas être surchargées :
- `getLaravel()`, `getConsole()`, `getParsed()`, `getStructure()`
- Toutes les méthodes d'accès aux arguments et flags
- `run()`, `call()`, `getCalls()`
- Les méthodes de sortie (`line()`, `info()`, `error()`, etc.)

### Bonnes pratiques

1. **Toujours documenter** : Ajouter une description avec `getDescription()`
2. **Utiliser les alias** : Rendre la directive facile à utiliser
3. **Gérer les erreurs** : Toujours retourner un `ExitCode` approprié
4. **Fournir des feedbacks** : Utiliser `info()`, `line()`, `error()`
5. **Tester les directives** : Utiliser `DirectiveTestingService`
---