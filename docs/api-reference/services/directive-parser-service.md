# DirectiveParserService - Référence Technique

## Description

Service de parsing et de validation des signatures de directives. Agit comme une couche d'abstraction autour du `SignatureParser` pour fournir une interface unifiée pour toutes les opérations de parsing.

## Hiérarchie / Implémentations

```
DirectiveParserInterface
    └── DirectiveParserService
        └── SignatureParser (délégation)
```

## Rôle principal

`DirectiveParserService` est le pont entre le système de directives et le moteur de parsing de signatures. Il permet de :

- Parser une requête contre une signature
- Valider la conformité d'une requête
- Valider la syntaxe d'une signature
- Gérer un registre de parseurs personnalisés
- Extraire les éléments d'une signature ou d'une requête

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `SignatureParser` - Moteur de parsing sous-jacent
- `StringTypedCollection` - Collection typée de chaînes
- PHP 8.1+

## API / Méthodes publiques

### `__construct(SignatureParser $parser)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parser` | `SignatureParser` | Instance du parseur de signatures |

**Retourne :** `void`

**Exemple :**
```php
$parser = new SignatureParser();
$service = new DirectiveParserService($parser);
```

---

### `parse(string $signature, string $query): ParsedSignatureRecord`

Parse une requête contre une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la signature |
| `$query` | `string` | Requête à parser |

**Retourne :** `ParsedSignatureRecord` - Données parsées

**Exceptions :** `InvalidArgumentException` - Si la signature ou la requête est invalide

**Exemple :**
```php
$signature = 'greet {name} {--formal}';
$query = 'greet John --formal';

$parsed = $service->parse($signature, $query);
// ParsedSignatureRecord avec 'name' => 'John', 'formal' => true
```

---

### `validate(string $signature, string $query): ValidationResultRecord`

Valide une requête contre une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la signature |
| `$query` | `string` | Requête à valider |

**Retourne :** `ValidationResultRecord` - Résultat de la validation

**Exceptions :** Aucune

**Exemple :**
```php
$result = $service->validate('greet {name}', 'greet');

if (!$result->isValid) {
    foreach ($result->errors as $error) {
        echo "Error: $error\n";
    }
}
```

---

### `isValid(string $signature, string $query): bool`

Vérifie si une requête est valide contre une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la signature |
| `$query` | `string` | Requête à vérifier |

**Retourne :** `bool` - `true` si la requête est valide

**Exceptions :** Aucune

**Exemple :**
```php
if ($service->isValid('greet {name}', 'greet John')) {
    echo "Query is valid\n";
}
```

---

### `getValidationErrors(string $signature, string $query): StringTypedCollection`

Récupère les erreurs de validation pour une requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition de la signature |
| `$query` | `string` | Requête à valider |

**Retourne :** `StringTypedCollection` - Collection des messages d'erreur

**Exceptions :** Aucune

**Exemple :**
```php
$errors = $service->getValidationErrors('greet {name}', 'greet');
foreach ($errors as $error) {
    echo "- $error\n";
}
// Affiche: "Missing required parameter: name"
```

---

### `validateSignature(string $signature): ValidationResultRecord`

Valide une définition de signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition à valider |

**Retourne :** `ValidationResultRecord` - Résultat de la validation

**Exceptions :** Aucune

**Exemple :**
```php
$result = $service->validateSignature('greet {name} {--formal}');
if ($result->isValid) {
    echo "Signature is valid\n";
}
```

---

### `isSignatureValid(string $signature): bool`

Vérifie si une définition de signature est valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Définition à vérifier |

**Retourne :** `bool` - `true` si la signature est valide

**Exceptions :** Aucune

**Exemple :**
```php
if ($service->isSignatureValid('greet {name} {--formal}')) {
    echo "Valid signature syntax\n";
}
```

---

### `addParser(ParserInterface $parser): self`

Ajoute un parseur personnalisé au registre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parser` | `ParserInterface` | Parseur à ajouter |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$customParser = new CustomSignatureParser();
$service->addParser($customParser);
// Le parseur personnalisé sera utilisé pour les signatures correspondantes
```

---

### `removeParser(string $parserClass): self`

Supprime un parseur du registre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parserClass` | `string` | Nom de classe du parseur à supprimer |

**Retourne :** `self` - Instance fluide

---

### `getParsers(): array`

Récupère tous les parseurs enregistrés.

**Retourne :** `array<int, ParserInterface>` - Liste des parseurs

**Exemple :**
```php
$parsers = $service->getParsers();
foreach ($parsers as $parser) {
    echo get_class($parser) . "\n";
}
```

---

### `extractSignatureElements(string $signature): StringTypedCollection`

Extrait les éléments individuels d'une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature à analyser |

**Retourne :** `StringTypedCollection` - Collection des éléments

**Exemple :**
```php
$signature = 'greet {name} {--formal} {--verbose}';
$elements = $service->extractSignatureElements($signature);
// Collection: ['greet', '{name}', '{--formal}', '{--verbose}']
```

---

### `extractQueryElements(string $query): StringTypedCollection`

Extrait les éléments individuels d'une requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Requête à analyser |

**Retourne :** `StringTypedCollection` - Collection des éléments

**Exemple :**
```php
$query = 'greet John --formal';
$elements = $service->extractQueryElements($query);
// Collection: ['greet', 'John', '--formal']
```

---

## Cas d'utilisation

### Cas 1 : Parsing d'une directive avec arguments

```php
<?php

use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\SignatureParser\SignatureParser;

$service = new DirectiveParserService(new SignatureParser());

// Signature avec arguments requis et optionnels
$signature = 'test-directive {name} {email} {format=json} {--force} {--verbose}';

// Requête complète
$query = 'test-directive John john@example.com xml --force';

$parsed = $service->parse($signature, $query);

echo "Command: " . $parsed->source . "\n";
echo "Name: " . $parsed->arguments['name'] . "\n";        // 'John'
echo "Email: " . $parsed->arguments['email'] . "\n";      // 'john@example.com'
echo "Format: " . $parsed->arguments['format'] . "\n";    // 'xml'
echo "Force: " . ($parsed->options['force'] ? 'true' : 'false') . "\n"; // true
echo "Verbose: " . ($parsed->options['verbose'] ? 'true' : 'false') . "\n"; // false
```

### Cas 2 : Validation avant exécution

```php
<?php

$service = new DirectiveParserService(new SignatureParser());

$signature = 'greet {name} {--formal}';

// Requête valide
if ($service->isValid($signature, 'greet John')) {
    echo "✅ Valid query\n";
}

// Requête invalide
if (!$service->isValid($signature, 'greet')) {
    $errors = $service->getValidationErrors($signature, 'greet');
    echo "❌ Validation failed:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}
// Affiche: "Missing required parameter: name"
```

### Cas 3 : Utilisation dans une directive

```php
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveParserService;

final class GreetDirective extends AbstractDirective
{
    private DirectiveParserService $parser;
    
    public function __construct()
    {
        $this->parser = new DirectiveParserService(new SignatureParser());
    }
    
    public function getSignature(): string
    {
        return 'greet {name} {--formal}';
    }
    
    public function execute(): ExitCode
    {
        $parsed = $this->parser->parse(
            $this->getSignature(),
            $this->query
        );
        
        $name = $parsed->arguments['name'];
        $formal = $parsed->options['formal'] ?? false;
        
        $greeting = $formal ? "Good day, $name" : "Hello, $name";
        $this->console->info($greeting);
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 4 : Validation des signatures des directives

```php
<?php

use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\SignatureParser\SignatureParser;

$service = new DirectiveParserService(new SignatureParser());

$directiveSignatures = [
    'list {--format}',
    'help {command?}',
    'test {name} {email} {format=zip} {files*} {--force}',
    'greet {name} {--formal}',
    'invalid {name', // Syntaxe invalide
];

foreach ($directiveSignatures as $signature) {
    $result = $service->validateSignature($signature);
    
    if ($result->isValid) {
        echo "✅ $signature\n";
    } else {
        echo "❌ $signature\n";
        foreach ($result->errors as $error) {
            echo "   - $error\n";
        }
    }
}
```

### Cas 5 : Gestion des erreurs de parsing

```php
<?php

$service = new DirectiveParserService(new SignatureParser());

try {
    $parsed = $service->parse('greet {name}', 'greet');
    echo "Parsed successfully\n";
} catch (InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // Affiche: "Missing required parameter: name"
}

// Utilisation avec validation pour éviter les exceptions
$signature = 'greet {name}';
$query = 'greet';

if ($service->isValid($signature, $query)) {
    $parsed = $service->parse($signature, $query);
    echo "Parsed: " . $parsed->arguments['name'] . "\n";
} else {
    $errors = $service->getValidationErrors($signature, $query);
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}
```

---

## Flux d'exécution

```
parse($signature, $query)
    ↓
SignatureParser->parse()
    ↓
Validation de la signature
    ├── Syntaxe valide → continuer
    └── Syntaxe invalide → InvalidArgumentException
    ↓
Parsing de la requête
    ├── Arguments positionnels → assignés par ordre
    ├── Arguments nommés → assignés par clé
    ├── Options (--option) → booléennes
    └── Options avec valeur (--option=value) → typées
    ↓
Validation des valeurs
    ├── Types (int, float, string, bool)
    ├── Valeurs par défaut
    └── Cardinalité (* pour multiples)
    ↓
Retourner ParsedSignatureRecord
```

### Validation des requêtes

```
validate($signature, $query)
    ↓
Parser la requête
    ↓
Comparer avec la signature
    ├── Arguments requis absents → erreur
    ├── Options inconnues → erreur
    ├── Types incompatibles → erreur
    └── Tout est valide → succès
    ↓
Retourner ValidationResultRecord
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Signature invalide | `InvalidArgumentException` | `Invalid signature: {message}` |
| Argument requis manquant | `InvalidArgumentException` | `Missing required parameter: {name}` |
| Option inconnue | `InvalidArgumentException` | `Unknown option: {name}` |
| Valeur de type incorrect | `InvalidArgumentException` | `Invalid value for {name}: expected {type}` |
| Syntaxe de la signature invalide | `InvalidArgumentException` | `Invalid signature syntax at {position}` |

**Méthodes sans exception :**
- `validate()` - Retourne un résultat avec erreurs
- `isValid()` - Retourne `false` en cas d'erreur
- `getValidationErrors()` - Retourne une collection vide si valide
- `validateSignature()` - Retourne un résultat avec erreurs
- `isSignatureValid()` - Retourne `false` en cas d'erreur

---

## Intégration

### Avec DirectiveKernel

```php
// Le kernel utilise le parser pour valider les requêtes
$kernel = DirectiveKernel::init($container);
$kernel->run($argv);
// Validation automatique des requêtes
```

### Avec DirectiveContainer

```php
$container = DirectiveContainer::create();
$parser = $container->make(DirectiveParserInterface::class);
// Le parser est automatiquement enregistré
```

### Ajout de parseurs personnalisés

```php
<?php

use AndyDefer\SignatureParser\Contracts\ParserInterface;

class JsonParser implements ParserInterface
{
    public function supports(string $signature): bool
    {
        return str_starts_with($signature, 'json:');
    }
    
    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        // Parsing JSON personnalisé
    }
}

// Enregistrement
$service->addParser(new JsonParser());
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `parse()` | O(n) | n = nombre de tokens dans la requête |
| `validate()` | O(n) | n = nombre de tokens dans la requête |
| `isValid()` | O(n) | n = nombre de tokens dans la requête |
| `validateSignature()` | O(n) | n = longueur de la signature |
| `extractSignatureElements()` | O(n) | n = longueur de la signature |

**Optimisations :**
- Le `SignatureParser` utilise un cache interne
- Les méthodes de validation sont optimisées pour les opérations fréquentes
- Les collections `StringTypedCollection` sont immuables pour la sécurité

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

use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\SignatureParser\SignatureParser;

// 1. Initialisation
$service = new DirectiveParserService(new SignatureParser());

// 2. Signatures à tester
$signatures = [
    'test-directive {name} {email} {format=zip} {files*} {--force} {--verbose}',
    'greet {name} {--formal}',
    'context:set {name}',
    'list {--format} {--short}',
];

// 3. Validation des signatures
echo "=== Signature Validation ===\n";
foreach ($signatures as $signature) {
    $result = $service->validateSignature($signature);
    echo ($result->isValid ? '✅' : '❌') . " $signature\n";
    
    if (!$result->isValid) {
        foreach ($result->errors as $error) {
            echo "  - $error\n";
        }
    }
}
echo "\n";

// 4. Parsing d'une requête
$signature = 'test-directive {name} {email} {format=zip} {files*} {--force} {--verbose}';
$query = 'test-directive John john@example.com json file1.txt file2.txt --force';

echo "=== Parsing ===\n";
echo "Signature: $signature\n";
echo "Query: $query\n\n";

try {
    $parsed = $service->parse($signature, $query);
    
    echo "Source: " . $parsed->source . "\n";
    echo "Arguments:\n";
    foreach ($parsed->arguments as $key => $value) {
        echo "  - $key: " . (is_array($value) ? implode(', ', $value) : $value) . "\n";
    }
    echo "Options:\n";
    foreach ($parsed->options as $key => $value) {
        echo "  - $key: " . ($value === true ? 'true' : $value) . "\n";
    }
} catch (InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Validation de requêtes
echo "=== Validation ===\n";
$testQueries = [
    'greet John --formal',
    'greet',
    'greet John --unknown',
    'context:set',
    'context:set John',
];

foreach ($testQueries as $query) {
    $signature = 'greet {name} {--formal}';
    $isValid = $service->isValid($signature, $query);
    
    echo ($isValid ? '✅' : '❌') . " $query\n";
    
    if (!$isValid) {
        $errors = $service->getValidationErrors($signature, $query);
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
}
echo "\n";

// 6. Extraction des éléments
$signature = 'test-directive {name} {email} {format=zip} {files*} {--force}';
echo "=== Element Extraction ===\n";
echo "Elements:\n";
$elements = $service->extractSignatureElements($signature);
foreach ($elements as $element) {
    echo "  - $element\n";
}
echo "\n";

$query = 'test-directive John john@example.com json file1.txt --force';
echo "Query elements:\n";
$elements = $service->extractQueryElements($query);
foreach ($elements as $element) {
    echo "  - $element\n";
}
echo "\n";

// 7. Gestion des erreurs
echo "=== Error Handling ===\n";
$invalidQueries = [
    ['greet {name}', 'greet'],
    ['greet {name} {--formal}', 'greet John --formal --extra'],
    ['test {name}', 'test 123'],
];

foreach ($invalidQueries as [$sig, $qry]) {
    echo "Query: $qry\n";
    $result = $service->validate($sig, $qry);
    
    if (!$result->isValid) {
        foreach ($result->errors as $error) {
            echo "  - $error\n";
        }
    }
    echo "\n";
}
```

## Voir aussi

- `SignatureParser` - Moteur de parsing sous-jacent
- `ParsedSignatureRecord` - Résultat du parsing
- `ValidationResultRecord` - Résultat de la validation
- `ParserInterface` - Interface des parseurs personnalisés
- `StringTypedCollection` - Collection typée de chaînes