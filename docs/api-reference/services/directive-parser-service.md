# DirectiveParserService - Référence Technique

## Description

Service responsable de l'analyse syntaxique des signatures de directives et de la transformation des arguments en paramètres typés. Il convertit une ligne de commande brute en une structure utilisable par les directives.

## Hiérarchie

```
DirectiveParserService
    ├── ParameterParserContext (stratégies de parsing)
    ├── ParameterOrderValidatorService (validation d'ordre)
    ├── ParameterExtractorService (extraction des paramètres)
    ├── OptionParserService (parsing des options)
    ├── ArgumentApplierService (application des arguments)
    └── ArgumentSplitterService (séparation des arguments variadiques)
```

## Rôle principal

Ce service analyse une signature de directive et une liste d'arguments bruts pour produire une structure typée contenant les arguments, options et arguments variadiques.

---

## Installation

```bash
composer require andydefer/laravel-directive
```

---

## API / Méthodes publiques

### `parse(string $signature, StringTypedCollection $argv): ParsedDirectiveRecord`

Analyse une signature et ses arguments.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la directive |
| `$argv` | `StringTypedCollection` | Collection des arguments bruts |

**Retourne :** `ParsedDirectiveRecord` - Structure contenant arguments, options et arguments variadiques

**Exceptions :** `InvalidArgumentException` - Signature invalide ou nombre d'arguments incorrect

**Exemple :**
```php
$service = new DirectiveParserService();
$argv = new StringTypedCollection();
$argv->add('John', '--role=admin');

$result = $service->parse('user:create {name} {--role=}', $argv);
$parsed = $service->toResult($result);

echo $parsed->arguments->get('name');   // 'John'
echo $parsed->options->get('role');     // 'admin'
```

### `extractHelp(string $signature): ParsedParameterCollection`

Extrait les informations d'aide d'une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la directive |

**Retourne :** `ParsedParameterCollection` - Collection des paramètres avec métadonnées (nom, type, requis, défaut)

**Exemple :**
```php
$help = $service->extractHelp('user:create {name} {email} {--role=admin}');
foreach ($help as $param) {
    echo $param->name;           // 'name', 'email', 'role'
    echo $param->type->value;    // 'argument', 'argument', 'option'
    echo $param->required;       // true, true, false
    echo $param->default;        // null, null, 'admin'
}
```

### `toResult(ParsedDirectiveRecord $parsed): ParsedResultRecord`

Convertit un enregistrement parsé en résultat utilisable avec collections typées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parsed` | `ParsedDirectiveRecord` | Enregistrement parsé |

**Retourne :** `ParsedResultRecord` - Résultat avec `ParsedArgumentCollection`, `ParsedOptionCollection` et `StringTypedCollection` pour variadiques

**Exemple :**
```php
$parsed = $service->parse($signature, $argv);
$result = $service->toResult($parsed);

// Accès aux arguments
$name = $result->arguments->get('name');

// Accès aux options
$role = $result->options->get('role');
$isVerbose = $result->options->isTrue('verbose');

// Accès aux arguments variadiques
foreach ($result->variadic_arguments as $file) {
    echo $file;
}
```

### `toJson(ParsedDirectiveRecord $parsed): string`

Convertit un enregistrement parsé en JSON.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parsed` | `ParsedDirectiveRecord` | Enregistrement parsé |

**Retourne :** `string` - Représentation JSON

**Exemple :**
```php
$json = $service->toJson($parsed);
// {"arguments":{"name":"John"},"options":{"role":"admin"},"variadic_arguments":[]}
```

---

## Syntaxe des signatures

### Ordre obligatoire des paramètres

> **⚠️ L'ordre des paramètres dans la signature est STRICT et obligatoire.**

```
{arguments requis} → {arguments avec valeur par défaut} → {arguments optionnels} → {arguments variadiques} → {options}
```

| Ordre | Type | Syntaxe | Exemple | Consomme une valeur ? |
|-------|------|---------|---------|----------------------|
| 1 | Argument requis | `{name}` | `{first_name} {last_name}` | ✅ Oui (obligatoire) |
| 2 | Argument avec valeur par défaut | `{role=user}` | `{role=admin} {status=active}` | ✅ Oui (surcharge possible) |
| 3 | Argument optionnel | `{count?}` | `{limit?} {offset?}` | ❌ Non (reste null) |
| 4 | Argument variadique | `{files*}` | `{files*} {tags*}` | ✅ Oui (tous les restants) |
| 5 | Option flag | `{--force}` | `{--verbose} {--quiet}` | ❌ Non |
| 6 | Option avec valeur | `{--role=}` | `{--format=} {--output=}` | ❌ Non |
| 7 | Option avec valeur par défaut | `{--format=json}` | `{--level=info}` | ❌ Non |

### Règles importantes

| Règle | Explication |
|-------|-------------|
| **Ordre strict** | Les types doivent apparaître dans l'ordre défini ci-dessus |
| **Un seul variadic** | Un seul argument variadique `{files*}` est autorisé par signature |
| **Variadic après optionnels** | L'argument variadique doit être placé APRÈS les arguments optionnels |
| **Options toujours à la fin** | Toutes les options doivent être placées APRÈS tous les arguments |

---

## Types de paramètres détaillés

### 1. Argument requis `{name}`

Arguments positionnels obligatoires.

```php
// Signature
public function getSignature(): string
{
    return 'user:create {first_name} {last_name}';
}

// Commande
./directive user:create John Doe

// Résultat
$first_name = 'John';  // obligatoire
$last_name = 'Doe';    // obligatoire
```

### 2. Argument avec valeur par défaut `{role=user}`

Arguments positionnels qui peuvent être surchargés.

```php
// Signature
public function getSignature(): string
{
    return 'user:list {limit=10}';
}

// Commande sans valeur
./directive user:list
// $limit = 10 (valeur par défaut)

// Commande avec valeur
./directive user:list 25
// $limit = 25 (valeur surchargée)
```

### 3. Argument optionnel `{count?}`

Arguments positionnels optionnels. Ne consomment PAS de valeur.

```php
// Signature
public function getSignature(): string
{
    return 'user:create {name?}';
}

// Commande avec valeur
./directive user:create John
// $name = null (l'argument optionnel ne consomme pas la valeur)

// Commande sans valeur
./directive user:create
// $name = null
```

> **⚠️ Important :** Les arguments optionnels ne consomment pas de valeur. Les valeurs passées sont automatiquement dirigées vers l'argument variadique s'il existe.

### 4. Argument variadique `{files*}`

Capture tous les arguments restants après les autres arguments.

```php
// Signature
public function getSignature(): string
{
    return 'process {name} {files*}';
}

// Commande
./directive process John file1.txt file2.txt file3.txt

// Résultat
$name = 'John';                    // argument requis
$files = ['file1.txt', 'file2.txt', 'file3.txt'];  // variadique
```

**Syntaxe avec crochets (recommandée pour la lisibilité) :**

```bash
# Sans crochets
./directive process John file1.txt file2.txt file3.txt

# Avec crochets (plus clair)
./directive process John [file1.txt, file2.txt, file3.txt]
```

### 5. Option flag `{--force}`

Option sans valeur, présente ou absente.

```php
// Signature
public function getSignature(): string
{
    return 'cache:clear {--force}';
}

// Commande
./directive cache:clear --force

// Résultat
$force = true;  // true si présent
```

### 6. Option avec valeur `{--role=}`

Option qui attend une valeur.

```php
// Signature
public function getSignature(): string
{
    return 'user:create {name} {--role=}';
}

// Commande
./directive user:create John --role=admin

// Résultat
$name = 'John';
$role = 'admin';
```

### 7. Option avec valeur par défaut `{--format=json}`

Option avec valeur par défaut si non spécifiée.

```php
// Signature
public function getSignature(): string
{
    return 'export {--format=json}';
}

// Commande sans valeur
./directive export
// $format = 'json'

// Commande avec valeur
./directive export --format=csv
// $format = 'csv'
```

---

## Exemples complets de signatures

### Signature valide

```php
// ✅ Tous les types dans le bon ordre
public function getSignature(): string
{
    return 'backup {source} {destination} {level=full} {options?} {excludes*} {--compress} {--format=zip} {--verbose}';
}
```

| Ordre | Type | Paramètre |
|-------|------|-----------|
| 1 | Argument requis | `{source}` |
| 2 | Argument requis | `{destination}` |
| 3 | Argument avec défaut | `{level=full}` |
| 4 | Argument optionnel | `{options?}` |
| 5 | Argument variadique | `{excludes*}` |
| 6 | Option flag | `{--compress}` |
| 7 | Option avec défaut | `{--format=zip}` |
| 8 | Option flag | `{--verbose}` |

### Signatures invalides

```php
// ❌ Option avant argument
public function getSignature(): string
{
    return '{--force} {name}';
}

// ❌ Argument requis après optionnel
public function getSignature(): string
{
    return '{name?} {email}';
}

// ❌ Argument avec défaut après optionnel
public function getSignature(): string
{
    return '{name?} {role=user}';
}

// ❌ Variadic avant optionnel
public function getSignature(): string
{
    return '{files*} {limit?}';
}

// ❌ Option variadique (non supporté)
public function getSignature(): string
{
    return '{--exclude*}';
}
```

---

## Cas d'utilisation

### Cas 1 : Directive de création d'utilisateur

```php
final class UserCreateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user:create {first_name} {last_name} {--role=user} {--notify}';
    }

    public function execute(): ExitCode
    {
        $firstName = $this->argument('first_name');
        $lastName = $this->argument('last_name');
        $role = $this->option('role') ?? 'user';
        $notify = $this->option('notify') ?? false;

        $this->info("User {$firstName} {$lastName} created with role {$role}");
        
        if ($notify) {
            $this->info("Notification sent");
        }

        return ExitCode::SUCCESS;
    }
}

// Commande: ./directive user:create John Doe --role=admin --notify
// Sortie: User John Doe created with role admin
//         Notification sent
```

### Cas 2 : Directive avec arguments variadiques

```php
final class ProcessFilesDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'process {name} {files*} {--verbose}';
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $files = $this->getVariadicArguments();
        $isVerbose = $this->option('verbose') ?? false;

        $this->info("Processing files for {$name}");
        
        foreach ($files as $file) {
            $this->line("  - {$file}");
            if ($isVerbose) {
                $this->info("    Done");
            }
        }

        return ExitCode::SUCCESS;
    }
}

// Commande: ./directive process John [file1.txt, file2.txt, file3.txt] --verbose
```

### Cas 3 : Utilisation directe du service

```php
// Pour tester ou manipuler manuellement
$service = new DirectiveParserService();
$argv = new StringTypedCollection();
$argv->add('John', '--role=admin');

$result = $service->parse('user:create {name} {--role=}', $argv);
$parsed = $service->toResult($parsed);

$name = $parsed->arguments->get('name');      // 'John'
$role = $parsed->options->get('role');        // 'admin'
$isActive = $parsed->options->isTrue('active'); // false
```

---

## Flux d'exécution

```
parse(signature, argv)
    │
    ├── 1. ArgumentSplitterService.split(argv)
    │       ├── [ → début des variadiques
    │       ├── , → séparateur
    │       └── ] → fin des variadiques
    │
    ├── 2. ParameterOrderValidatorService.validate(signature)
    │       ├── required (1) → default (2) → optional (3) → variadic (4) → option (5)
    │
    ├── 3. ParameterExtractorService.extract(signature)
    │
    ├── 4. Séparation options / arguments normaux
    │       ├── OptionParserService.isOption() → options
    │       └── → arguments normaux
    │
    ├── 5. OptionParserService.parseOptions() → parse les options
    │
    ├── 6. ArgumentApplierService.apply()
    │       ├── required → consomme une valeur
    │       ├── default → utilise défaut ou consomme
    │       ├── optional → ne consomme PAS
    │       └── variadic → consomme tous les restants
    │
    └── 7. → ParsedDirectiveRecord
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Argument requis manquant | `InvalidArgumentException` | `Not enough arguments (missing: "name")` |
| Trop d'arguments (sans variadic) | `InvalidArgumentException` | `Too many arguments provided` |
| Option invalide | `InvalidArgumentException` | `Unknown option: --xxx` |
| Ordre des paramètres invalide | `InvalidArgumentException` | `Invalid signature format: required arguments must come before arguments with default values. Problem with: {name}` |
| Option avec valeur mal formée | `InvalidArgumentException` | `Invalid option format: --role` |

---

## Types supportés - Récapitulatif

| Type | Syntaxe | Consomme une valeur ? | Supporté |
|------|---------|----------------------|----------|
| Argument requis | `{name}` | ✅ Oui (obligatoire) | ✅ |
| Argument avec valeur par défaut | `{role=user}` | ✅ Oui (surcharge possible) | ✅ |
| Argument optionnel | `{count?}` | ❌ Non | ✅ |
| Argument variadique | `{files*}` | ✅ Oui (tous les restants) | ✅ |
| Option flag | `{--force}` | ❌ Non | ✅ |
| Option avec valeur | `{--role=}` | ❌ Non | ✅ |
| Option avec valeur par défaut | `{--format=json}` | ❌ Non | ✅ |
| Option variadique | `{--exclude*}` | N/A | ❌ Non supporté |

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

// 1. Créer le service
$parser = new DirectiveParserService();

// 2. Définir la signature
$signature = 'backup {source} {destination} {compress?} {excludes*} {--format=zip} {--verbose}';

// 3. Simuler des arguments de ligne de commande
$argv = new StringTypedCollection();
$argv->add('/home/user/data');
$argv->add('/backup/');
$argv->add('--format=tar');
$argv->add('[', 'temp,', 'cache,', 'logs', ']');
$argv->add('--verbose');

// 4. Parser
$result = $parser->parse($signature, $argv);
$parsed = $parser->toResult($result);

// 5. Utiliser les résultats
echo "Source: " . $parsed->arguments->get('source') . "\n";        // /home/user/data
echo "Destination: " . $parsed->arguments->get('destination') . "\n";  // /backup/
echo "Format: " . $parsed->options->get('format') . "\n";           // tar (surclasse zip)
echo "Verbose: " . ($parsed->options->isTrue('verbose') ? 'yes' : 'no') . "\n";  // yes

echo "Excludes:\n";
foreach ($parsed->variadic_arguments as $exclude) {
    echo "  - {$exclude}\n";  // temp, cache, logs
}

// 6. Exporter en JSON
$json = $parser->toJson($result);
echo $json;
// {"arguments":{"source":"\/home\/user\/data","destination":"\/backup\/"},"options":{"format":"tar","verbose":true},"variadic_arguments":["temp","cache","logs"]}

// 7. Extraire l'aide
$help = $parser->extractHelp($signature);
foreach ($help as $param) {
    echo "{$param->name} ({$param->type->value})";
    if ($param->required) echo " [required]";
    if ($param->default !== null) echo " [default: {$param->default}]";
    echo "\n";
}
// source (argument) [required]
// destination (argument) [required]
// compress (argument) [default: null]
// excludes (variadic_argument)
// format (option) [default: zip]
// verbose (option)
```

## Voir aussi

- [`AbstractDirective`](abstract-directive.md) - Classe de base des directives
- [`DirectiveInteractionService`](directive-interaction-service.md) - Service d'interaction utilisateur
- [`ParameterCollection`](../collections/parameter-collection.md) - Collection typée pour paramètres
- [`ParsedResultRecord`](../records/parsed-result-record.md) - Record de résultat parsé
---