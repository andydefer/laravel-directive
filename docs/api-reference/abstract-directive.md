# AbstractDirective - Référence Technique

## Description

Classe de base abstraite pour toutes les directives. Fournit les fonctionnalités communes d'exécution, de parsing des arguments, de gestion des flags, de méthodes de sortie console et d'appels internes.

## Hiérarchie / Implémentations

```
DirectiveInterface
    └── AbstractDirective
        ├── BuiltIn\ListDirective
        ├── BuiltIn\HelpDirective
        ├── BuiltIn\VersionDirective
        ├── BuiltIn\CleanLogsDirective
        └── ... (Directives personnalisées)
```

## Rôle principal

`AbstractDirective` est le fondement de tout le système de directives. Elle permet de :

- Définir une signature de commande avec arguments, options et flags
- Parser automatiquement les requêtes entrantes
- Accéder aux arguments, aux valeurs par défaut et aux flags
- Gérer un contexte partagé entre les directives
- Exécuter des appels internes à d'autres directives
- Détecter les dépendances circulaires
- Fournir des méthodes de sortie console (info, error, table, etc.)
- Définir des hooks `beforeExecute()` et `afterExecute()`

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `Console` - Sortie console
- `Container` - Conteneur de dépendances
- `DirectiveParserService` - Parsing des signatures
- `DirectiveKernel` - Noyau d'exécution
- PHP 8.1+

## API / Méthodes publiques

### `__construct(DirectiveKernel $kernel, string $query = '')`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$kernel` | `DirectiveKernel` | Noyau d'exécution |
| `$query` | `string` | Requête à exécuter |

**Retourne :** `void`

**Exemple :**
```php
$directive = new GreetDirective($kernel, 'John --formal');
```

---

### `getSignature(): string` (abstract)

Retourne la signature de la directive.

**Retourne :** `string` - Signature (ex: `greet {name} {--formal}`)

**Exemple :**
```php
public function getSignature(): string
{
    return 'greet {name} {--formal}';
}
```

---

### `getAliases(): StringTypedCollection`

Retourne la liste des alias de la directive.

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```php
public function getAliases(): StringTypedCollection
{
    return StringTypedCollection::from(['hello', 'hi']);
}
```

---

### `getDescription(): string` (abstract)

Retourne la description de la directive.

**Retourne :** `string` - Description

**Exemple :**
```php
public function getDescription(): string
{
    return 'Say hello to someone';
}
```

---

### `argument(string $key): mixed`

Récupère la valeur d'un argument.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Nom de l'argument |

**Retourne :** `mixed` - Valeur de l'argument ou `null`

**Exemple :**
```php
$name = $this->argument('name');
```

---

### `hasArgument(string $key): bool`

Vérifie si un argument existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Nom de l'argument |

**Retourne :** `bool` - `true` si l'argument existe

---

### `flag(string $key): bool`

Récupère la valeur d'un flag (option booléenne).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Nom du flag |

**Retourne :** `bool` - Valeur du flag

**Exemple :**
```php
if ($this->flag('verbose')) {
    $this->info('Verbose mode enabled');
}
```

---

### `hasFlag(string $key): bool`

Vérifie si un flag existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Nom du flag |

**Retourne :** `bool` - `true` si le flag existe

---

### `isFlagActive(string $key): bool`

Vérifie si un flag est actif.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Nom du flag |

**Retourne :** `bool` - `true` si le flag est actif

---

### `getVariadicArguments(): StringTypedCollection`

Récupère les arguments variadiques.

**Retourne :** `StringTypedCollection` - Collection des valeurs variadiques

**Exemple :**
```php
$files = $this->getVariadicArguments();
foreach ($files as $file) {
    echo "Processing: $file\n";
}
```

---

### `hasVariadicArguments(): bool`

Vérifie si des arguments variadiques sont présents.

**Retourne :** `bool` - `true` si des arguments variadiques existent

---

### `getRequiredArguments(): array`

Récupère tous les arguments requis.

**Retourne :** `array<string, mixed>` - Tableau des arguments requis

---

### `getDefaultArguments(): array`

Récupère tous les arguments avec valeurs par défaut.

**Retourne :** `array<string, mixed>` - Tableau des arguments par défaut

---

### `getFlags(): array`

Récupère tous les flags.

**Retourne :** `array<string, bool>` - Tableau des flags

---

### `getActiveFlags(): array`

Récupère les flags actifs.

**Retourne :** `array<int, string>` - Noms des flags actifs

---

### `line(string $message): void`

Affiche une ligne de texte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message à afficher |

---

### `info(string $message): void`

Affiche un message d'information (vert).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message à afficher |

---

### `error(string $message): void`

Affiche un message d'erreur (rouge).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message à afficher |

---

### `newLine(): void`

Affiche une ligne vide.

---

### `separator(string $character = '-', int $length = 80): void`

Affiche une ligne de séparation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$character` | `string` | Caractère de séparation |
| `$length` | `int` | Longueur de la ligne |

---

### `ask(string $question): string`

Demande une entrée utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$question` | `string` | Question à poser |

**Retourne :** `string` - Réponse de l'utilisateur

---

### `confirm(string $question): bool`

Demande une confirmation (oui/non).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$question` | `string` | Question à poser |

**Retourne :** `bool` - `true` si l'utilisateur répond oui

---

### `table(ListCollection|array $headers, ListCollection|array $rows): void`

Affiche un tableau.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$headers` | `ListCollection\|array` | En-têtes du tableau |
| `$rows` | `ListCollection\|array` | Lignes du tableau |

---

### `contextGet(string $key, mixed $default = null): mixed`

Récupère une valeur du contexte partagé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du contexte |
| `$default` | `mixed` | Valeur par défaut |

**Retourne :** `mixed` - Valeur du contexte

---

### `contextSet(string $key, mixed $value): void`

Définit une valeur dans le contexte partagé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du contexte |
| `$value` | `mixed` | Valeur à définir |

---

### `contextHas(string $key): bool`

Vérifie si une clé existe dans le contexte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé à vérifier |

**Retourne :** `bool` - `true` si la clé existe

---

### `contextAll(): MapCollection`

Récupère tout le contexte.

**Retourne :** `MapCollection` - Contexte complet

---

### `contextMerge(array $data): void`

Fusionne des données dans le contexte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Données à fusionner |

---

### `contextRemove(string $key): void`

Supprime une clé du contexte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé à supprimer |

---

### `contextClear(): void`

Efface tout le contexte.

---

### `contextIncrement(string $key, int $step = 1): int`

Incrémente une valeur numérique dans le contexte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du contexte |
| `$step` | `int` | Pas d'incrémentation |

**Retourne :** `int` - Nouvelle valeur

---

### `contextDecrement(string $key, int $step = 1): int`

Décrémente une valeur numérique dans le contexte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du contexte |
| `$step` | `int` | Pas de décrémentation |

**Retourne :** `int` - Nouvelle valeur

---

## Cas d'utilisation

### Cas 1 : Directive simple avec arguments

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class GreetDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'greet {name} {--formal}';
    }
    
    public function getDescription(): string
    {
        return 'Say hello to someone';
    }
    
    protected function execute(): ExitCode
    {
        $name = $this->argument('name');
        $formal = $this->flag('formal');
        
        $greeting = $formal ? "Good day, $name" : "Hello, $name";
        $this->info($greeting);
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 2 : Directive avec arguments variadiques

```php
<?php

final class ProcessFilesDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'process {files*} {--verbose}';
    }
    
    public function getDescription(): string
    {
        return 'Process multiple files';
    }
    
    protected function execute(): ExitCode
    {
        $files = $this->getVariadicArguments();
        $verbose = $this->flag('verbose');
        
        $this->info("Processing " . $files->count() . " files");
        
        foreach ($files as $file) {
            if ($verbose) {
                $this->line("  - Processing: $file");
            }
            // Traitement du fichier...
        }
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 3 : Directive avec contexte partagé

```php
<?php

final class UserContextDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user:set {name}';
    }
    
    protected function execute(): ExitCode
    {
        $name = $this->argument('name');
        
        // Stocker dans le contexte partagé
        $this->contextSet('user_name', $name);
        $this->contextSet('last_update', date('Y-m-d H:i:s'));
        
        $this->info("User set to: $name");
        
        // Une autre directive peut accéder à ce contexte
        return ExitCode::SUCCESS;
    }
}

final class UserGetDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user:get';
    }
    
    protected function execute(): ExitCode
    {
        $name = $this->contextGet('user_name', 'anonymous');
        $lastUpdate = $this->contextGet('last_update', 'never');
        
        $this->info("Current user: $name (last update: $lastUpdate)");
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 4 : Directive avec appels internes

```php
<?php

final class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {--force}';
    }
    
    protected function execute(): ExitCode
    {
        $this->info('Starting deployment...');
        
        // Appeler d'autres directives
        $this->call('build --clean');
        $this->call('test --unit');
        $this->call('migrate --force');
        
        if ($this->flag('force')) {
            $this->call('cache:clear');
        }
        
        $this->info('Deployment completed!');
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 5 : Directive avec hooks before/after

```php
<?php

final class TransactionDirective extends AbstractDirective
{
    private array $transactions = [];
    
    public function getSignature(): string
    {
        return 'transaction {action}';
    }
    
    protected function beforeExecute(): void
    {
        $this->info('=== Starting transaction ===');
        $this->transactions = [];
        $this->contextSet('transaction_start', microtime(true));
    }
    
    protected function execute(): ExitCode
    {
        $action = $this->argument('action');
        
        // Logique de transaction...
        $this->transactions[] = $action;
        $this->info("Executed: $action");
        
        return ExitCode::SUCCESS;
    }
    
    protected function afterExecute(ExitCode $exitCode): void
    {
        $duration = microtime(true) - $this->contextGet('transaction_start');
        
        $this->separator('=');
        $this->info("Transaction completed in " . round($duration, 3) . "s");
        $this->info("Actions executed: " . count($this->transactions));
        $this->separator('=');
    }
}
```

---

## Flux d'exécution

```
new Directive($kernel, $query)
    ↓
__construct()
    ├── kernel = $kernel
    ├── context = kernel->getContext()
    ├── console = container->make(Console::class)
    ├── parser = container->make(DirectiveParserService::class)
    ├── parsed = parser->parse($signature, $query)
    └── structure = new SignatureStructureVO($signature)
    ↓
run()
    ↓
beforeExecute() (hook)
    ├── Succès → continuer
    └── Exception → RUNTIME_ERROR
    ↓
execute() (méthode abstraite)
    ├── Logique métier de la directive
    ├── Appels internes via call()
    └── Retourne ExitCode
    ↓
executeCalls()
    ├── Pour chaque appel enregistré
    │   ├── Trouver la directive
    │   ├── Vérifier les dépendances circulaires
    │   └── Exécuter la directive
    └── Retourner ExitCode
    ↓
afterExecute($exitCode) (hook)
    ↓
Retourner ExitCode final
```

### Détection des dépendances circulaires

```
isCircularCall($directive, $query)
    ↓
$stackKey = $directive->class . '|' . $query
    ↓
Vérifier si $stackKey dans $executionStack
    ├── Oui → CONFLICT (circulaire détectée)
    └── Non → continuer
    ↓
Exécuter la directive
    ↓
Ajouter $stackKey à $executionStack
    ↓
Supprimer après exécution
```

---

## Gestion des erreurs

| Situation | Exception | Comportement |
|-----------|-----------|--------------|
| beforeExecute échoue | `Throwable` | `RUNTIME_ERROR` + message d'erreur |
| execute échoue | `Throwable` | `RUNTIME_ERROR` + message d'erreur |
| Appel interne échoue | `Throwable` | `RUNTIME_ERROR` + message d'erreur |
| Dépendance circulaire | Détection | `CONFLICT` + message d'alerte |
| Directive introuvable | Aucune | `NOT_FOUND` + message d'erreur |

---

## Intégration

### Création d'une directive personnalisée

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class MyCustomDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'my:command {param} {--option} {--verbose}';
    }
    
    public function getDescription(): string
    {
        return 'My custom command description';
    }
    
    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['mc']);
    }
    
    protected function beforeExecute(): void
    {
        if ($this->flag('verbose')) {
            $this->info('Verbose mode enabled');
        }
    }
    
    protected function execute(): ExitCode
    {
        $param = $this->argument('param');
        $option = $this->flag('option');
        
        $this->line("Param: $param");
        $this->line("Option: " . ($option ? 'yes' : 'no'));
        
        return ExitCode::SUCCESS;
    }
    
    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->newLine();
        $this->info("Execution completed with exit code: " . $exitCode->value);
    }
}
```

### Enregistrement dans le discovery

```php
$discovery = DirectiveDiscoveryService::init($container);
$discovery->addDirective(MyCustomDirective::class);
$discovery->addDirectives([
    AnotherDirective::class,
    DeployDirective::class,
]);
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `__construct()` | O(n) | Parsing de la signature |
| `argument()` | O(1) | Accès au tableau parsé |
| `flag()` | O(1) | Accès au tableau parsé |
| `call()` | O(1) | Ajout à la liste des appels |
| `run()` | O(n) | n = nombre d'appels internes |
| Contexte `contextGet/Set` | O(1) | Opération sur MapCollection |

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

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class CompleteDirective extends AbstractDirective
{
    // 1. Définition de la signature
    public function getSignature(): string
    {
        return 'complete {name} {email} {format=json} {files*} {--force} {--verbose}';
    }
    
    // 2. Description
    public function getDescription(): string
    {
        return 'Complete example directive with all features';
    }
    
    // 3. Alias
    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['comp', 'c']);
    }
    
    // 4. Hook before
    protected function beforeExecute(): void
    {
        if ($this->flag('verbose')) {
            $this->info('=== Starting execution ===');
        }
        
        // Initialiser le contexte
        $this->contextSet('execution_start', microtime(true));
        $this->contextSet('processed_files', []);
    }
    
    // 5. Logique principale
    protected function execute(): ExitCode
    {
        // Récupération des arguments
        $name = $this->argument('name');
        $email = $this->argument('email');
        $format = $this->argument('format');
        $files = $this->getVariadicArguments();
        $force = $this->flag('force');
        $verbose = $this->flag('verbose');
        
        // Affichage
        $this->separator('=');
        $this->info('Processing...');
        $this->separator('-');
        
        $this->line("Name: $name");
        $this->line("Email: $email");
        $this->line("Format: $format");
        $this->line("Files: " . $files->count());
        $this->line("Force: " . ($force ? 'yes' : 'no'));
        
        // Traitement des fichiers
        $processed = [];
        foreach ($files as $file) {
            if ($verbose) {
                $this->line("Processing: $file");
            }
            $processed[] = $file;
        }
        
        // Mise à jour du contexte
        $this->contextSet('processed_files', $processed);
        $this->contextSet('last_file', end($processed));
        
        // Appels internes conditionnels
        if ($force) {
            $this->call('clean --all');
        }
        
        $this->call('log "Processed ' . count($processed) . ' files"');
        
        // Tableau récapitulatif
        $this->newLine();
        $this->info('Summary:');
        $headers = ['Item', 'Value'];
        $rows = [
            ['Name', $name],
            ['Email', $email],
            ['Format', $format],
            ['Files processed', count($processed)],
            ['Force mode', $force ? 'Yes' : 'No'],
        ];
        $this->table($headers, $rows);
        
        return ExitCode::SUCCESS;
    }
    
    // 6. Hook after
    protected function afterExecute(ExitCode $exitCode): void
    {
        $duration = microtime(true) - $this->contextGet('execution_start');
        $processed = count($this->contextGet('processed_files', []));
        
        $this->separator('=');
        $this->info("Execution completed");
        $this->line("Duration: " . round($duration, 3) . "s");
        $this->line("Files processed: $processed");
        $this->line("Exit code: " . $exitCode->value . " (" . $exitCode->getLabel() . ")");
        
        if ($exitCode->isSuccess()) {
            $this->info('✅ Success!');
        } else {
            $this->error('❌ Failed!');
        }
    }
}
```

## Voir aussi

- `DirectiveInterface` - Interface de la directive
- `DirectiveKernel` - Noyau d'exécution
- `DirectiveParserService` - Parsing des signatures
- `ExitCode` - Énumération des codes de sortie
- `Console` - Service de sortie console
- `MapCollection` - Collection clé-valeur pour le contexte