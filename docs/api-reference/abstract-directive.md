# AbstractDirective - Référence Technique

## Description

`AbstractDirective` est la classe de base abstraite pour toutes les directives du système. Elle fournit les fonctionnalités communes nécessaires au parsing des arguments, à la gestion des flags, à l'exécution des appels internes et à l'interaction avec la console.

## Hiérarchie / Implémentations

```
DirectiveInterface
    └── AbstractDirective
            ├── BuiltIn\ListDirective
            ├── BuiltIn\HelpDirective
            ├── BuiltIn\VersionDirective
            ├── BuiltIn\CleanLogsDirective
            ├── BuiltIn\KernelAuditDirective
            └── ... (autres directives)
```

## Rôle principal

`AbstractDirective` agit comme une classe de base pour toutes les directives concrètes. Elle encapsule :

- Le parsing des arguments et flags via `DirectiveParserService`
- L'accès aux valeurs typées via des méthodes dédiées (`getArgument()`, `getFlag()`, etc.)
- La gestion du contexte partagé entre directives
- L'exécution d'appels internes avec détection de circularité
- Les méthodes de sortie console (héritées de `Console`)
- La journalisation des problèmes via le noyau

---

## Installation

```bash
composer require andydefer/laravel-directive
```

### Dépendances

- PHP 8.1+
- `DirectiveKernel` - Noyau d'exécution
- `Console` - Composant de sortie console
- `SignatureParser` - Parser de signatures
- `Container` - Conteneur de dépendances

---

## API / Méthodes publiques

### `__construct(DirectiveKernel $kernel, string $query = '')`

Construit une nouvelle instance de directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$kernel` | `DirectiveKernel` | Noyau d'exécution |
| `$query` | `string` | Requête à exécuter (vide par défaut) |

**Exemple :**
```php
$directive = new MyDirective($kernel, 'John --force');
```

---

### `getApplication(): ?Container`

Retourne le conteneur de dépendances.

**Retourne :** `?Container` - Instance du conteneur ou `null`

**Exemple :**
```php
$container = $directive->getApplication();
$logger = $container->make(Logger::class);
```

---

### `getKernel(): ?DirectiveKernel`

Retourne le noyau d'exécution.

**Retourne :** `?DirectiveKernel` - Instance du noyau ou `null`

**Exemple :**
```php
$kernel = $directive->getKernel();
$kernel->addProblem('test', 'Context', 'Message');
```

---

### `getConsole(): Console`

Retourne l'instance de la console pour les sorties.

**Retourne :** `Console` - Instance de la console

**Exemple :**
```php
$console = $directive->getConsole();
$console->title('Mon Titre');
```

---

### `getParsed(): ParsedSignatureRecord`

Retourne le résultat du parsing de la signature et de la requête.

**Retourne :** `ParsedSignatureRecord` - Données parsées

**Exemple :**
```php
$parsed = $directive->getParsed();
echo $parsed->source; // 'greet'
```

---

### `getStructure(): SignatureStructureVO`

Retourne la structure de la signature.

**Retourne :** `SignatureStructureVO` - Structure de la signature

**Exemple :**
```php
$structure = $directive->getStructure();
$requireds = $structure->getRequireds(); // ['name', 'email']
```

---

### `getArgument(string $key): mixed`

Récupère un argument par son nom en cherchant dans l'ordre de priorité.

**Ordre de recherche :**
1. Arguments requis
2. Arguments par défaut
3. Énumérations
4. Arguments variadiques (retourne un tableau)
5. Flags (retourne un booléen)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'argument |

**Retourne :** `mixed` - Valeur de l'argument ou `null` si non trouvé

**Exemple :**
```php
$name = $directive->getArgument('name'); // 'John'
$format = $directive->getArgument('format'); // 'json'
$files = $directive->getArgument('files'); // ['file1.txt', 'file2.txt']
$force = $directive->getArgument('force'); // true
```

---

### `hasArgument(string $key): bool`

Vérifie si un argument existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'argument |

**Retourne :** `bool` - `true` si l'argument existe, `false` sinon

**Exemple :**
```php
if ($directive->hasArgument('name')) {
    echo $directive->getArgument('name');
}
```

---

### `getRequired(string $key): ?string`

Récupère la valeur d'un argument requis.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'argument |

**Retourne :** `?string` - La valeur ou `null`

---

### `getRequireds(): array`

Récupère tous les arguments requis.

**Retourne :** `array<string, string>` - Tableau associatif [nom => valeur]

**Exemple :**
```php
$requireds = $directive->getRequireds();
// ['name' => 'John', 'email' => 'john@example.com']
```

---

### `getDefault(string $key): ?string`

Récupère la valeur d'un argument par défaut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'argument |

**Retourne :** `?string` - La valeur ou `null`

---

### `getDefaults(): array`

Récupère tous les arguments par défaut.

**Retourne :** `array<string, string|null>` - Tableau associatif [nom => valeur]

**Exemple :**
```php
$defaults = $directive->getDefaults();
// ['format' => 'zip', 'output' => null]
```

---

### `getEnum(string $key): mixed`

Récupère la valeur d'une énumération.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'énumération |

**Retourne :** `mixed` - La valeur ou `null`

---

### `getEnums(): array`

Récupère toutes les énumérations.

**Retourne :** `array<string, mixed>` - Tableau associatif [nom => valeur]

---

### `getEnumAllowedValues(string $key): ?array`

Récupère les valeurs autorisées pour une énumération.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'énumération |

**Retourne :** `?array` - Liste des valeurs autorisées ou `null`

**Exemple :**
```php
$allowed = $directive->getEnumAllowedValues('level');
// ['low', 'medium', 'high']
```

---

### `isEnumRequired(string $key): bool`

Vérifie si une énumération est requise.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'énumération |

**Retourne :** `bool` - `true` si requise, `false` sinon

---

### `isEnumOptional(string $key): bool`

Vérifie si une énumération est optionnelle.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'énumération |

**Retourne :** `bool` - `true` si optionnelle, `false` sinon

---

### `isEnumValueAllowed(string $key, string $value): bool`

Vérifie si une valeur est autorisée pour une énumération.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'énumération |
| `$value` | `string` | Valeur à vérifier |

**Retourne :** `bool` - `true` si autorisée, `false` sinon

**Exemple :**
```php
if ($directive->isEnumValueAllowed('level', 'master')) {
    echo "'master' est une valeur valide";
}
```

---

### `getVariadic(string $key): array`

Récupère les valeurs d'un argument variadique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'argument variadique |

**Retourne :** `array<string>` - Liste des valeurs ou tableau vide

---

### `getVariadics(): array`

Récupère tous les arguments variadiques.

**Retourne :** `array<string, array<string>>` - Tableau associatif [nom => valeurs]

---

### `hasVariadic(string $key): bool`

Vérifie si un argument variadique existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de l'argument variadique |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `getFlag(string $key): bool`

Récupère la valeur d'un flag.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du flag (sans le préfixe `--`) |

**Retourne :** `bool` - `true` si actif, `false` sinon

**Exemple :**
```php
$force = $directive->getFlag('force'); // true
```

---

### `getFlags(): array`

Récupère tous les flags.

**Retourne :** `array<string, bool>` - Tableau associatif [nom => booléen]

---

### `hasFlag(string $key): bool`

Vérifie si un flag existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du flag |

**Retourne :** `bool` - `true` s'il existe, `false` sinon

---

### `isFlagActive(string $key): bool`

Vérifie si un flag est actif.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du flag |

**Retourne :** `bool` - `true` si actif, `false` sinon

---

### `getActiveFlags(): array`

Récupère tous les flags actifs.

**Retourne :** `array<string>` - Liste des noms des flags actifs

**Exemple :**
```php
$active = $directive->getActiveFlags(); // ['force', 'verbose']
```

---

### `getVariadicArguments(): StringTypedCollection`

Récupère tous les arguments variadiques sous forme de collection plate.

**Retourne :** `StringTypedCollection` - Collection de toutes les valeurs variadiques

**Exemple :**
```php
$allValues = $directive->getVariadicArguments();
foreach ($allValues as $value) {
    echo $value . "\n";
}
```

---

### `hasVariadicArguments(): bool`

Vérifie s'il y a des arguments variadiques.

**Retourne :** `bool` - `true` s'il y en a, `false` sinon

---

### `hasRequireds(): bool`

Vérifie s'il y a des arguments requis.

**Retourne :** `bool`

---

### `hasDefaults(): bool`

Vérifie s'il y a des arguments par défaut.

**Retourne :** `bool`

---

### `hasEnums(): bool`

Vérifie s'il y a des énumérations.

**Retourne :** `bool`

---

### `hasFlags(): bool`

Vérifie s'il y a des flags.

**Retourne :** `bool`

---

### `line(string $message): void`

Affiche une ligne de texte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message à afficher |

---

### `info(string $message): void`

Affiche un message d'information (couleur bleue).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message à afficher |

---

### `error(string $message): void`

Affiche un message d'erreur (couleur rouge).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message à afficher |

---

### `newLine(): void`

Affiche une nouvelle ligne.

---

### `separator(string $character = '-', int $length = 80): void`

Affiche une ligne de séparation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$character` | `string` | Caractère à répéter |
| `$length` | `int` | Longueur de la ligne |

---

### `ask(string $question): string`

Pose une question à l'utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$question` | `string` | Question à poser |

**Retourne :** `string` - Réponse de l'utilisateur

---

### `confirm(string $question): bool`

Demande une confirmation à l'utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$question` | `string` | Question à poser |

**Retourne :** `bool` - `true` si confirmé, `false` sinon

---

### `table(ListCollection|array $headers, ListCollection|array $rows): void`

Affiche un tableau.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$headers` | `ListCollection|array` | En-têtes du tableau |
| `$rows` | `ListCollection|array` | Lignes du tableau |

---

### `call(string $query): void`

Queue un appel interne vers une autre directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Requête à exécuter |

**Exemple :**
```php
protected function execute(): ExitCode
{
    $this->call('backup database');
    $this->call('clean cache');
    
    return ExitCode::SUCCESS;
}
```

---

### `getCalls(): array`

Récupère tous les appels internes queués.

**Retourne :** `array<DirectiveCallRecord>` - Liste des appels

---

### `getAliases(): StringTypedCollection`

Récupère les alias de la directive.

**Retourne :** `StringTypedCollection` - Collection des alias

---

### `run(): ExitCode`

Exécute la directive avec les hooks `beforeExecute()` et `afterExecute()`.

**Retourne :** `ExitCode` - Code de sortie

**Exceptions :** `Throwable` - Les exceptions sont capturées et transformées en problèmes

---

### `beforeExecute(): void`

Hook appelé avant l'exécution principale. À surcharger dans les directives concrètes.

---

### `afterExecute(ExitCode $exitCode): void`

Hook appelé après l'exécution principale. À surcharger dans les directives concrètes.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$exitCode` | `ExitCode` | Code de sortie de l'exécution |

---

## Cas d'utilisation

### Cas 1 : Création d'une directive simple

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

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

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['hello']);
    }

    protected function execute(): ExitCode
    {
        $name = $this->getRequired('name');
        $formal = $this->getFlag('formal');

        if ($formal) {
            $this->info("Good day, {$name}!");
        } else {
            $this->info("Hello, {$name}!");
        }

        return ExitCode::SUCCESS;
    }
}
```

### Cas 2 : Directive avec énumérations

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class SetLevelDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'set-level ::level->[low,medium,high]=medium {--verbose}';
    }

    public function getDescription(): string
    {
        return 'Set the priority level';
    }

    protected function execute(): ExitCode
    {
        $level = $this->getEnum('level');
        $verbose = $this->getFlag('verbose');

        if ($verbose) {
            $this->info("Level set to: {$level}");
        }

        return ExitCode::SUCCESS;
    }
}
```

### Cas 3 : Directive avec appel interne

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {environment} {--force}';
    }

    public function getDescription(): string
    {
        return 'Deploy the application';
    }

    protected function execute(): ExitCode
    {
        $env = $this->getRequired('environment');
        $force = $this->getFlag('force');

        $this->info("Deploying to {$env}...");

        if ($force) {
            $this->call('backup database');
            $this->call('clear cache');
        } else {
            $this->call('validate --strict');
        }

        $this->call('run migrations');

        return ExitCode::SUCCESS;
    }
}
```

### Cas 4 : Directive avec contexte partagé

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ContextDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context {action} {key} {value?}';
    }

    public function getDescription(): string
    {
        return 'Manipulate shared context';
    }

    protected function execute(): ExitCode
    {
        $action = $this->getRequired('action');
        $key = $this->getRequired('key');
        $value = $this->getArgument('value');

        switch ($action) {
            case 'set':
                $this->contextSet($key, $value);
                $this->info("Set {$key} = {$value}");
                break;

            case 'get':
                $value = $this->contextGet($key);
                $this->info("{$key} = " . ($value ?? 'null'));
                break;

            case 'increment':
                $new = $this->contextIncrement($key);
                $this->info("{$key} = {$new}");
                break;
        }

        return ExitCode::SUCCESS;
    }
}
```

---

## Flux d'exécution

```
new Directive($kernel, $query)
    ↓
__construct()
    ├── kernel → $this->kernel
    ├── context → $this->context
    ├── console → $this->console
    ├── parser → $this->parser
    ├── parsed → parser->parse(signature, query)
    └── structure → new SignatureStructureVO(signature)
    ↓
run()
    ├── beforeExecute() (hook)
    │   └── Si exception → RUNTIME_ERROR
    ├── execute() (logique métier)
    │   ├── Appels à getArgument(), getFlag(), etc.
    │   ├── Appels à call() pour les appels internes
    │   └── Retourne ExitCode
    ├── executeCalls()
    │   ├── Pour chaque call()
    │   │   ├── extractCommandName()
    │   │   ├── findDirective()
    │   │   ├── isCircularCall() (détection de circularité)
    │   │   └── executeDirectiveInstance()
    │   └── Retourne ExitCode
    ├── afterExecute(exitCode) (hook)
    └── Retourne ExitCode
```

---

## Gestion des erreurs

| Situation | Erreur | Message |
|-----------|--------|---------|
| Erreur dans `beforeExecute()` | Problème kernel | `directive_before_hook` |
| Erreur dans `execute()` | Problème kernel | `directive_execute_hook` |
| Appel interne vers directive inexistante | Problème kernel | `call_directive_not_found` |
| Appel interne circulaire | Problème kernel | `circular_call_detected` |
| Erreur dans l'exécution d'un appel interne | Problème kernel | `execute_call_instance` |

---

## Performance

- **Parsing** : O(n) où n est le nombre de tokens dans la signature
- **Recherche d'arguments** : O(1) via les collections typées
- **Appels internes** : O(m) où m est le nombre d'appels queués
- **Détection de circularité** : O(1) via la pile d'exécution

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

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class BackupDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'backup {source}#"Source directory" {destination} {format=zip} ::level->[low,medium,high]=medium {excludes*} {--force} {--verbose}';
    }

    public function getDescription(): string
    {
        return 'Create a backup of files and directories';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['bk', 'save']);
    }

    protected function beforeExecute(): void
    {
        if ($this->getFlag('verbose')) {
            $this->info('Starting backup process...');
        }
    }

    protected function execute(): ExitCode
    {
        $source = $this->getRequired('source');
        $destination = $this->getRequired('destination');
        $format = $this->getDefault('format');
        $level = $this->getEnum('level');
        $excludes = $this->getVariadic('excludes');
        $force = $this->getFlag('force');
        $verbose = $this->getFlag('verbose');

        if ($verbose) {
            $this->info('Source: ' . $source);
            $this->info('Destination: ' . $destination);
            $this->info('Format: ' . $format);
            $this->info('Level: ' . $level);
            $this->info('Excludes: ' . implode(', ', $excludes));
            $this->info('Force: ' . ($force ? 'Yes' : 'No'));
        }

        // Simulation de la logique de backup
        $this->call('validate --strict');
        $this->call('compress --level=' . $level);

        if ($force) {
            $this->call('clean --all');
        }

        $this->info('✅ Backup completed successfully!');

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        if ($this->getFlag('verbose')) {
            $this->info('Backup process finished with code: ' . $exitCode->value);
        }
    }
}
```

---

## Voir aussi

- `DirectiveInterface` - Interface des directives
- `DirectiveKernel` - Noyau d'exécution
- `DirectiveParserService` - Service de parsing
- `ExitCode` - Énumération des codes de sortie
- `SignatureParser` - Parser de signatures
- `SignatureStructureVO` - Structure de signature