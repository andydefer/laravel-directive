## AbstractDirective - Référence Technique (Mise à jour)

## Description

`AbstractDirective` est la classe de base abstraite pour toutes les directives du système. Elle fournit les fonctionnalités communes nécessaires au parsing des arguments, à la gestion des flags, à l'exécution des appels internes, à la manipulation du contexte partagé, à l'interaction avec la console et à la gestion des données personnalisées via des tags.

## Hiérarchie / Implémentations

```
DirectiveInterface
    └── AbstractDirective
            ├── BuiltIn\ListDirective
            ├── BuiltIn\HelpDirective
            ├── BuiltIn\VersionDirective
            ├── BuiltIn\CleanLogsDirective
            ├── BuiltIn\KernelAuditDirective
            └── ... (autres directives personnalisées)
```

**Interfaces implémentées :** `DirectiveInterface`

## Rôle principal

`AbstractDirective` agit comme une classe de base pour toutes les directives concrètes. Elle encapsule :

- Le parsing des arguments et flags via `DirectiveParserService`
- L'accès aux valeurs typées via des méthodes dédiées (`getArgument()`, `getFlag()`, etc.)
- La gestion des données personnalisées via des tags (`<key="value">`)
- La gestion du contexte partagé entre directives
- L'exécution d'appels internes avec détection de circularité
- Les méthodes de sortie console (héritées du composant `Console`)
- La journalisation des problèmes via le noyau
- Les hooks d'exécution (`beforeExecute()`, `afterExecute()`)

## Installation

```bash
composer require andydefer/laravel-directive
```

### Dépendances

- PHP 8.1+
- `DirectiveKernel` - Noyau d'exécution
- `Console` - Composant de sortie console
- `SignatureParser` - Parser de signatures
- `Application` - Conteneur Laravel
- `StrictDataObject` - Objet de données typé

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

### `getApplication(): ?Application`

Retourne l'application Laravel.

**Retourne :** `?Application` - Instance de l'application ou `null`

**Exemple :**
```php
$app = $directive->getApplication();
$logger = $app->make(Logger::class);
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

### `getCustomData(): StrictDataObject`

Retourne toutes les données personnalisées extraites des tags personnalisés.

**Retourne :** `StrictDataObject` - Objet contenant toutes les données personnalisées

**Exemple :**
```php
$customData = $directive->getCustomData();
// StrictDataObject ['description' => 'User profile data', 'version' => '1.0']
```

---

### `getCustomDataItem(string $key, mixed $default = null): mixed`

Récupère une valeur spécifique des données personnalisées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de la donnée personnalisée |
| `$default` | `mixed` | Valeur par défaut si la clé n'existe pas |

**Retourne :** `mixed` - La valeur ou `$default` si non trouvée

**Exemple :**
```php
$description = $directive->getCustomDataItem('description', 'Default description');
echo $description; // 'User profile data'
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

### Cas 1 : Création d'une directive avec tags personnalisés

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class ForgeDataDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'forge:data {name} <description="">';
    }

    public function getDescription(): string
    {
        return 'Create a new data DTO class';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['create-data']);
    }

    protected function execute(): ExitCode
    {
        $name = $this->getRequired('name');
        
        // Récupération de la description via tag personnalisé
        $description = $this->getCustomDataItem('description', 'Data DTO for ' . $name);

        $this->info("Creating data: {$name}");
        $this->info("Description: {$description}");

        return ExitCode::SUCCESS;
    }
}
```

**Utilisation :**
```bash
./directive forge:data user <description="User profile data transfer object">
# Output: Creating data: user
#         Description: User profile data transfer object
```

---

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

---

### Cas 3 : Directive avec arguments variadiques

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class DeleteDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'delete {files*} {--force}';
    }

    public function getDescription(): string
    {
        return 'Delete multiple files';
    }

    protected function execute(): ExitCode
    {
        $files = $this->getVariadic('files');
        $force = $this->getFlag('force');

        if (empty($files)) {
            $this->error('No files specified');
            return ExitCode::INVALID_ARGUMENT;
        }

        $this->info(sprintf('Deleting %d file(s)...', count($files)));

        foreach ($files as $file) {
            if ($force) {
                $this->info("Force deleting: {$file}");
            } else {
                $this->info("Deleting: {$file}");
            }
        }

        return ExitCode::SUCCESS;
    }
}
```

---

### Cas 4 : Directive avec appels internes

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

    protected function beforeExecute(): void
    {
        $this->info('Starting deployment...');
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

        $this->call('migrate --force');

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->info('Deployment completed with code: ' . $exitCode->value);
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
    ├── context → $this->kernel->getContext()
    ├── console → $this->kernel->getApplication()->make(Console::class)
    ├── parser → $this->kernel->getApplication()->make(DirectiveParserService::class)
    ├── parsed → parser->parse(signature, query)
    │   ├── source
    │   ├── requireds
    │   ├── defaults
    │   ├── variadics
    │   ├── flags
    │   ├── enums
    │   └── custom_data (tags personnalisés)
    └── structure → new SignatureStructureVO(signature)
    ↓
run()
    ├── beforeExecute() (hook)
    │   └── Si exception → RUNTIME_ERROR + problème enregistré
    ├── execute() (logique métier)
    │   ├── Appels à getArgument(), getFlag(), etc.
    │   ├── Appels à getCustomData(), getCustomDataItem()
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
    │   └── Si exception → RUNTIME_ERROR + problème enregistré
    └── Retourne ExitCode
```

---

## Voir aussi

- `DirectiveInterface` - Interface des directives
- `DirectiveKernel` - Noyau d'exécution
- `DirectiveParserService` - Service de parsing
- `ExitCode` - Énumération des codes de sortie
- `SignatureParser` - Parser de signatures
- `SignatureStructureVO` - Structure de signature
- `ParsedSignatureRecord` - Données parsées
- `StrictDataObject` - Objet de données typé pour les tags personnalisés