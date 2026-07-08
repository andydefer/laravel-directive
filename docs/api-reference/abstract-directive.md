# AbstractDirective - Référence Technique

## Description

Classe abstraite de base pour toutes les directives CLI. Fournit l'accès aux arguments parsés, aux options, aux variadiques, à la console et aux hooks d'exécution.

## Hiérarchie / Implémentations

```
DirectiveInterface
    └── AbstractDirective
            ├── HelpDirective
            ├── ListDirective
            ├── VersionDirective
            └── [Vos directives personnalisées]
```

## Rôle principal

`AbstractDirective` est le socle de toutes les directives. Il :

- Parse automatiquement la signature et la requête via `DirectiveParserService`
- Fournit des méthodes d'accès aux arguments et options
- Gère les appels à d'autres directives (`call()`)
- Propose des hooks d'exécution (`beforeExecute()`, `afterExecute()`)
- Délègue les sorties console à `Console`

## Installation

```bash
composer require andydefer/laravel-directive
```

### Prérequis

- Laravel 10.x, 11.x, 12.x
- PHP 8.1+

## API / Méthodes publiques

### `__construct(Application $app, string $query)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$app` | `Application` | Instance de l'application Laravel |
| `$query` | `string` | La commande complète (ex: `user:create John john@example.com`) |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$directive = new MyDirective($app, 'user:create John john@example.com');
```

---

### `getLaravel(): Application`

Retourne l'instance de l'application Laravel.

**Retourne :** `Application`

**Exemple :**
```php
$app = $directive->getLaravel();
$config = $app->make(Config::class);
```

---

### `getConsole(): Console`

Retourne l'instance de la console pour les sorties.

**Retourne :** `Console`

**Exemple :**
```php
$console = $directive->getConsole();
$console->info('Message');
```

---

### `getParsed(): ParsedSignatureRecord`

Retourne la structure parsée de la signature et de la requête.

**Retourne :** `ParsedSignatureRecord`

**Exemple :**
```php
$parsed = $directive->getParsed();
$source = $parsed->source; // 'user:create'
```

---

### `argument(string $key): mixed`

Retourne la valeur d'un argument (requis ou par défaut).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Nom de l'argument |

**Retourne :** `mixed` - La valeur de l'argument ou `null`

**Exemple :**
```php
$name = $directive->argument('name'); // 'John'
$format = $directive->argument('format'); // 'zip' (valeur par défaut)
```

---

### `hasArgument(string $key): bool`

Vérifie si un argument existe (requis ou par défaut).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Nom de l'argument |

**Retourne :** `bool`

**Exemple :**
```php
if ($directive->hasArgument('name')) {
    $name = $directive->argument('name');
}
```

---

### `option(string $key): bool`

Retourne la valeur d'une option (flag).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Nom de l'option |

**Retourne :** `bool` - `true` si l'option est active

**Exemple :**
```php
if ($directive->option('force')) {
    echo "Force mode enabled";
}
```

---

### `hasOption(string $key): bool`

Vérifie si une option est active.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Nom de l'option |

**Retourne :** `bool`

**Exemple :**
```php
if ($directive->hasOption('verbose')) {
    $this->info('Verbose mode');
}
```

---

### `getVariadicArguments(): StringTypedCollection`

Retourne tous les arguments variadiques.

**Retourne :** `StringTypedCollection`

**Exemple :**
```php
$files = $directive->getVariadicArguments();
foreach ($files as $file) {
    echo "Processing: $file\n";
}
```

---

### `hasVariadicArguments(): bool`

Vérifie s'il y a des arguments variadiques.

**Retourne :** `bool`

**Exemple :**
```php
if ($directive->hasVariadicArguments()) {
    $files = $directive->getVariadicArguments();
}
```

---

### `line(string $message): void`

Affiche une ligne simple.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message à afficher |

**Retourne :** `void`

**Exemple :**
```php
$directive->line('Hello World');
```

---

### `info(string $message): void`

Affiche un message d'information (bleu).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message à afficher |

**Retourne :** `void`

**Exemple :**
```php
$directive->info('Loading...');
```

---

### `error(string $message): void`

Affiche un message d'erreur (rouge).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message à afficher |

**Retourne :** `void`

**Exemple :**
```php
$directive->error('Operation failed');
```

---

### `newLine(): void`

Affiche une nouvelle ligne.

**Retourne :** `void`

---

### `separator(string $character = '-', int $length = 80): void`

Affiche une ligne de séparation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$character` | `string` | Caractère de séparation |
| `$length` | `int` | Longueur de la ligne |

**Retourne :** `void`

**Exemple :**
```php
$directive->separator('=', 50);
```

---

### `ask(string $question): string`

Demande une saisie utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$question` | `string` | Question à poser |

**Retourne :** `string` - Réponse de l'utilisateur

**Exemple :**
```php
$name = $directive->ask('What is your name?');
```

---

### `confirm(string $question): bool`

Demande une confirmation Oui/Non.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$question` | `string` | Question à poser |

**Retourne :** `bool` - `true` si confirmé

**Exemple :**
```php
if ($directive->confirm('Continue?')) {
    // ...
}
```

---

### `table(ListCollection|array $headers, ListCollection|array $rows): void`

Affiche un tableau formaté.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$headers` | `ListCollection\|array` | En-têtes du tableau |
| `$rows` | `ListCollection\|array` | Lignes du tableau |

**Retourne :** `void`

**Exemple :**
```php
$directive->table(
    ['Name', 'Email'],
    [
        ['John', 'john@example.com'],
        ['Jane', 'jane@example.com'],
    ]
);
```

---

### `call(string $query): void`

Appelle une autre directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Commande à exécuter |

**Retourne :** `void`

**Exemple :**
```php
$this->call('list');
$this->call('backup /var/www /backup --force');
```

---

### `getCalls(): array`

Retourne tous les appels enregistrés.

**Retourne :** `array<DirectiveCallRecord>`

**Exemple :**
```php
$calls = $directive->getCalls();
foreach ($calls as $call) {
    echo $call->query;
}
```

---

### `run(): ExitCode`

Exécute la directive avec les hooks `beforeExecute()` et `afterExecute()`.

**Retourne :** `ExitCode` - Code de sortie

**Exceptions :** `Throwable` - Les exceptions sont capturées et retournent `ExitCode::RUNTIME_ERROR`

**Exemple :**
```php
$exitCode = $directive->run(); // ExitCode::SUCCESS
```

---

### `getAliases(): StringTypedCollection`

Retourne les alias de la directive.

**Retourne :** `StringTypedCollection`

**Exemple :**
```php
$aliases = $directive->getAliases(); // ['ls', '-l']
```

---

### `getSignature(): string` (abstract)

Retourne la signature de la directive.

**Retourne :** `string`

**Exemple :**
```php
public function getSignature(): string
{
    return 'user:create {name} {email} {--force}';
}
```

---

### `execute(): ExitCode` (abstract)

Contient la logique métier de la directive.

**Retourne :** `ExitCode`

**Exemple :**
```php
protected function execute(): ExitCode
{
    $name = $this->argument('name');
    $this->info("Hello $name");
    return ExitCode::SUCCESS;
}
```

---

## Hooks

### `beforeExecute(): void`

Exécuté avant `execute()`. Utile pour les validations.

**Retourne :** `void`

**Exceptions :** `Throwable` - Si une exception est levée, la directive s'arrête et retourne `ExitCode::RUNTIME_ERROR`

**Exemple :**
```php
protected function beforeExecute(): void
{
    $source = $this->argument('source');
    if (!is_dir($source)) {
        throw new \RuntimeException("Source directory not found: $source");
    }
}
```

---

### `afterExecute(ExitCode $exitCode): void`

Exécuté après `execute()`. Utile pour le nettoyage ou le logging.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$exitCode` | `ExitCode` | Code de sortie de la directive |

**Retourne :** `void`

**Exceptions :** Les exceptions sont capturées et affichées en erreur

**Exemple :**
```php
protected function afterExecute(ExitCode $exitCode): void
{
    if ($exitCode->isSuccess()) {
        $this->info('Operation completed successfully');
    } else {
        $this->error('Operation failed');
    }
}
```

---

## Cas d'utilisation

### Cas 1 : Directive de backup avec validation

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class BackupDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'backup {source} {destination} {--force}';
    }

    protected function beforeExecute(): void
    {
        $source = $this->argument('source');
        if (!is_dir($source)) {
            throw new \RuntimeException("Source directory not found: $source");
        }
    }

    protected function execute(): ExitCode
    {
        $source = $this->argument('source');
        $destination = $this->argument('destination');
        
        $this->info("Backing up from $source to $destination");
        // Logique de backup...
        
        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        if ($exitCode->isSuccess()) {
            $this->info('Backup completed successfully');
        }
    }
}
```

### Cas 2 : Directive avec appels imbriqués

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {env} {--force}';
    }

    protected function execute(): ExitCode
    {
        $env = $this->argument('env');
        
        $this->info("Deploying to $env");
        
        // Appeler d'autres directives
        $this->call('backup /var/www /backup');
        $this->call('migrate --force');
        $this->call('cache:clear');
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 3 : Directive avec interactions utilisateur

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class UserCreateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user:create {--admin}';
    }

    protected function execute(): ExitCode
    {
        $name = $this->ask('Enter username:');
        $email = $this->ask('Enter email:');
        
        $isAdmin = $this->option('admin');
        
        if ($this->confirm("Create user $name with admin rights?")) {
            // Créer l'utilisateur
            $this->info("User $name created");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

---

## Flux d'exécution

```
run()
    ↓
try {
    beforeExecute()
    ↓
    execute() → ExitCode
    ↓
    afterExecute($exitCode)
} catch (Throwable $e) {
    afterExecute(RUNTIME_ERROR)
    error($e->getMessage())
    return RUNTIME_ERROR
}
    ↓
Retourne ExitCode
```

---

## Gestion des erreurs

| Situation | Comportement | Code de sortie |
|-----------|--------------|----------------|
| beforeExecute() lance une exception | Capture, affiche erreur | `ExitCode::RUNTIME_ERROR` |
| execute() lance une exception | afterExecute appelé, capture, affiche erreur | `ExitCode::RUNTIME_ERROR` |
| afterExecute() lance une exception | Capture, affiche erreur | `ExitCode::RUNTIME_ERROR` |
| Opération réussie | - | `ExitCode::SUCCESS` |
| Appel circulaire détecté | Avertissement, arrêt | `ExitCode::CONFLICT` |
| Directive non trouvée | Message d'erreur | `ExitCode::NOT_FOUND` |

---

## Intégration

### Dans une application Laravel

```php
// Dans un ServiceProvider
$this->app->singleton(BackupDirective::class, function ($app) {
    return new BackupDirective($app, $query);
});
```

### Avec DirectiveExecutionService

```php
$executionService = app(DirectiveExecutionService::class);
$exitCode = $executionService->execute('backup /var/www /backup --force');
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `argument()` | O(1) | Accès direct aux collections parsées |
| `option()` | O(1) | Accès direct aux collections parsées |
| `call()` | O(1) | Ajout à la liste des calls |
| `run()` | O(n) | n = nombre de calls imbriqués |

---

## Compatibilité

| Version Laravel | Support | Notes |
|-----------------|---------|-------|
| Laravel 15.x | ✅ Complet | Support total |
| Laravel 14.x | ✅ Complet | Support total |
| Laravel 13.x | ✅ Complet | Support total |
| Laravel 12.x | ✅ Complet | Support total |
| Laravel 11.x | ✅ Complet | Support total |
| Laravel 10.x | ✅ Complet | Support total |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class ProcessDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'process {input} {output} {--verbose} {--force}';
    }

    public function getDescription(): string
    {
        return 'Process files with optional flags';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['proc', 'p']);
    }

    protected function beforeExecute(): void
    {
        $input = $this->argument('input');
        if (!file_exists($input)) {
            throw new \RuntimeException("Input file not found: $input");
        }
    }

    protected function execute(): ExitCode
    {
        $input = $this->argument('input');
        $output = $this->argument('output');

        if ($this->option('verbose')) {
            $this->info("Processing $input -> $output");
        }

        // Logique de traitement...
        $this->call('validate ' . $input);

        if ($this->option('force')) {
            $this->info('Force mode enabled');
        }

        $this->line('Done');
        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        if ($exitCode->isSuccess()) {
            $this->info('✅ Processing completed successfully');
        } else {
            $this->error('❌ Processing failed');
        }
    }
}
```

## Voir aussi

- `DirectiveInterface` - Interface des directives
- `DirectiveExecutionService` - Service d'exécution
- `Console` - Composant de sortie console
- `ParsedSignatureRecord` - Structure des données parsées
- `ExitCode` - Codes de sortie disponibles
---