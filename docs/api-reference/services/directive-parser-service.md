# DirectiveParserService - Référence Technique

## Description

Service de parsing et de validation des signatures de directives. Il agit comme un wrapper autour du `SignatureParser`, fournissant une interface unifiée pour toutes les opérations de parsing : validation des signatures, parsing des requêtes, et gestion des parsers personnalisés.

## Hiérarchie / Implémentations

```
ParserRegistryInterface
    └── SignatureParserInterface
        └── DirectiveParserInterface
            └── DirectiveParserService (final)
```

## Rôle principal

Centraliser toutes les opérations de parsing de signatures dans une interface unique. Le service permet de :
1. Parser les requêtes utilisateur contre une signature
2. Valider la syntaxe des signatures
3. Gérer un registre de parsers personnalisés
4. Extraire les éléments d'une signature ou d'une requête

## Installation

### Dépendances

```bash
# Le service dépend du package SignatureParser
composer require andydefer/signature-parser
```

### Configuration dans le conteneur

```php
// Dans le service provider
$this->app->singleton(DirectiveParserInterface::class, function ($app) {
    return new DirectiveParserService(
        new SignatureParser()
    );
});
```

## API / Méthodes publiques

### `parse(string $signature, string $query): ParsedSignatureRecord`

Parse une requête utilisateur contre une définition de signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature (ex: `"user:create {name} {--admin}"`) |
| `$query` | `string` | La requête à parser (ex: `"John --admin"`) |

**Retourne :** `ParsedSignatureRecord` - Les données parsées (arguments, flags, etc.)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$result = $parser->parse('user:create {name} {--admin}', 'John --admin');
// ParsedSignatureRecord avec les données
```

---

### `validate(string $signature, string $query): ValidationResultRecord`

Valide une requête contre une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature |
| `$query` | `string` | La requête à valider |

**Retourne :** `ValidationResultRecord` - Résultat de la validation

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$result = $parser->validate('user:create {name}', '');
// ValidationResultRecord avec isValid = false
```

---

### `isValid(string $signature, string $query): bool`

Vérifie rapidement si une requête est valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature |
| `$query` | `string` | La requête à vérifier |

**Retourne :** `bool` - `true` si la requête est valide, `false` sinon

**Exceptions :** Aucune

**Exemple :**
```php
<?php

if ($parser->isValid('user:create {name}', 'John')) {
    echo "Requête valide";
}
```

---

### `getValidationErrors(string $signature, string $query): StringTypedCollection`

Récupère les erreurs de validation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature |
| `$query` | `string` | La requête validée |

**Retourne :** `StringTypedCollection` - Collection des messages d'erreur

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$errors = $parser->getValidationErrors('user:create {name}', '');

foreach ($errors as $error) {
    echo "❌ " . $error . PHP_EOL;
}
```

---

### `validateSignature(string $signature): ValidationResultRecord`

Valide une définition de signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature à valider |

**Retourne :** `ValidationResultRecord` - Résultat de la validation

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$result = $parser->validateSignature('user:create {name}');
// Vérifie la syntaxe de la signature
```

---

### `isSignatureValid(string $signature): bool`

Vérifie rapidement si une signature est syntaxiquement valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature à vérifier |

**Retourne :** `bool` - `true` si la signature est valide, `false` sinon

**Exceptions :** Aucune

**Exemple :**
```php
<?php

if ($parser->isSignatureValid('user:create {name}')) {
    echo "Signature valide";
}
```

---

### `addParser(ParserInterface $parser): self`

Ajoute un parser personnalisé au registre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parser` | `ParserInterface` | Le parser à ajouter |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$parser->addParser(new CustomParser());
```

---

### `removeParser(string $parserClass): self`

Retire un parser du registre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parserClass` | `string` | Le nom de classe du parser à retirer |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$parser->removeParser(CustomParser::class);
```

---

### `getParsers(): array`

Récupère tous les parsers enregistrés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, ParserInterface>` - Liste des parsers enregistrés

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$parsers = $parser->getParsers();
foreach ($parsers as $p) {
    echo get_class($p) . PHP_EOL;
}
```

---

### `extractSignatureElements(string $signature): StringTypedCollection`

Extrait les éléments individuels d'une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La signature à analyser |

**Retourne :** `StringTypedCollection` - Collection des éléments

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$elements = $parser->extractSignatureElements('user:create {name} {--admin}');
// ['user:create', '{name}', '{--admin}']
```

---

### `extractQueryElements(string $query): StringTypedCollection`

Extrait les éléments individuels d'une requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | La requête à analyser |

**Retourne :** `StringTypedCollection` - Collection des éléments

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$elements = $parser->extractQueryElements('John --admin');
// ['John', '--admin']
```

## Format des signatures

### Syntaxe de base

```
commande {argument} {argument?} {argument=default} {--flag} {--flag=value} {arguments*}
```

### Éléments supportés

| Élément | Syntaxe | Exemple |
|---------|---------|---------|
| Arguments requis | `{nom}` | `{name}` |
| Arguments optionnels | `{nom?}` | `{name?}` |
| Arguments par défaut | `{nom=default}` | `{name=John}` |
| Flags (booléens) | `{--nom}` | `{--admin}` |
| Flags avec valeur | `{--nom=valeur}` | `{--format=gzip}` |
| Arguments variadiques | `{nom*}` | `{files*}` |

### Exemples de signatures

```php
// Simple
'list'

// Avec argument
'user:create {name}'

// Avec arguments multiples
'user:create {name} {email} {--admin}'

// Avec arguments optionnels et flags
'backup {file?} {--force} {--compression=gzip}'

// Avec arguments variadiques
'copy {source*} {--recursive}'
```

## Cas d'utilisation

### Cas 1 : Parsing d'une requête utilisateur

```php
<?php

$parser = app(DirectiveParserInterface::class);
$signature = 'backup {file?} {--force} {--compression=gzip}';

// Requête utilisateur : "backup database.sql --force"
$result = $parser->parse($signature, 'backup database.sql --force');

// Accès aux arguments
$file = $result->required->get('file') ?? $result->default->get('file');
$force = $result->flags->get('force');
$compression = $result->flags->get('compression');

echo "Fichier: " . ($file ?? 'aucun') . PHP_EOL;
echo "Force: " . ($force ? 'oui' : 'non') . PHP_EOL;
echo "Compression: " . ($compression ?? 'gzip') . PHP_EOL;
```

### Cas 2 : Validation interactive

```php
<?php

class InteractiveDirective extends AbstractDirective
{
    public function execute(): ExitCode
    {
        $parser = $this->getParser();
        
        // Valider la requête
        $result = $parser->validate($this->getSignature(), $this->getQuery());
        
        if (!$result->isValid()) {
            foreach ($result->getErrors() as $error) {
                $this->error($error);
            }
            return ExitCode::INVALID_ARGUMENT;
        }
        
        // Si valide, parser
        $parsed = $parser->parse($this->getSignature(), $this->getQuery());
        // ... utiliser les données
    }
}
```

### Cas 3 : Extensions avec parsers personnalisés

```php
<?php

use AndyDefer\SignatureParser\Contracts\ParserInterface;

class CustomParser implements ParserInterface
{
    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        // Logique de parsing personnalisée
    }
}

$parser = app(DirectiveParserInterface::class);
$parser->addParser(new CustomParser());

// Le parser personnalisé est maintenant disponible
```

### Cas 4 : Extraction et analyse

```php
<?php

$parser = app(DirectiveParserInterface::class);

// Extraire les éléments pour analyse
$signatureElements = $parser->extractSignatureElements('user:create {name} {--admin}');
$queryElements = $parser->extractQueryElements('John --admin');

echo "Signature elements: " . $signatureElements->join(', ') . PHP_EOL;
echo "Query elements: " . $queryElements->join(', ') . PHP_EOL;
```

## Flux d'exécution

```
DirectiveParserService::parse($signature, $query)
    │
    └── $this->parser->parse($signature, $query)
        │
        ├── Validation de la signature
        ├── Parsing des arguments
        │   ├── Arguments requis → required
        │   ├── Arguments optionnels → default
        │   ├── Arguments variadiques → variadic
        │   └── Flags → flags
        ├── Validation de la requête
        │   ├── Présence des arguments requis
        │   ├── Types des flags
        │   └── Valeurs par défaut
        │
        └── Retourne ParsedSignatureRecord
            ├── required (TypedRecord)
            ├── default (TypedRecord)
            ├── variadic (VariadicCollection)
            ├── flags (FlagCollection)
            └── source (string)
```

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Signature invalide | `ValidationResultRecord` avec `isValid = false` | `Invalid signature: {message}` |
| Requête invalide | `ValidationResultRecord` avec `isValid = false` | `Missing required argument: {name}` |
| Argument manquant | `ValidationResultRecord` avec `isValid = false` | `Required argument "{name}" is missing` |
| Format de flag invalide | `ValidationResultRecord` avec `isValid = false` | `Invalid flag format: {flag}` |
| Type inattendu | `ValidationResultRecord` avec `isValid = false` | `Unexpected token: {token}` |

### Messages d'erreur typiques

```php
// Signature invalide
"Invalid signature: Missing closing brace"

// Argument requis manquant
"Required argument 'name' is missing"

// Flag invalide
"Invalid flag format: --admin=value should be --admin=value"

// Token inattendu
"Unexpected token: '--force' at position 5"
```

## Intégration

Le `DirectiveParserService` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `SignatureParser` | Parsing des signatures et requêtes |
| `AbstractDirective` | Parsing des arguments et flags |
| `DirectiveDiscoveryService` | Validation des signatures réservées |
| `ParserInterface` | Extensibilité via parsers personnalisés |

### Utilisation dans AbstractDirective

```php
abstract class AbstractDirective
{
    private DirectiveParserService $parser;
    
    public function __construct(Application $app, string $query)
    {
        $this->parser = $app->make(DirectiveParserService::class);
        $this->parsed = $this->parser->parse($this->getSignature(), $query);
    }
}
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(n) | n = nombre d'éléments à parser |
| Temps typique | 1-5ms | Parsing simple |
| Mémoire | < 1KB | Données parsées |
| Cache | ❌ Non | Parsing à chaque appel |

### Facteurs de performance

1. **Longueur de la signature** : Plus la signature est complexe, plus le parsing est lent
2. **Nombre d'arguments** : Plus d'arguments → plus de temps de parsing
3. **Flags** : Les flags avec valeurs ajoutent de la complexité
4. **Parsers personnalisés** : Des parsers complexes peuvent ralentir le processus

### Optimisations

```php
class CachedParserService
{
    private array $parseCache = [];
    private array $validateCache = [];
    
    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        $key = md5($signature . '|' . $query);
        
        if (isset($this->parseCache[$key])) {
            return $this->parseCache[$key];
        }
        
        $result = $this->parser->parse($signature, $query);
        $this->parseCache[$key] = $result;
        return $result;
    }
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| PHP 8.3+ | ✅ Complet | - |
| SignatureParser 1.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\SignatureParser\SignatureParser;

// Créer le parser
$parser = new DirectiveParserService(new SignatureParser());

// 1. Définir une signature
$signature = 'user:create {name} {email?} {--admin} {--role=user} {tags*}';

// 2. Valider la signature
$validation = $parser->validateSignature($signature);
if (!$validation->isValid()) {
    foreach ($validation->getErrors() as $error) {
        echo "❌ " . $error . PHP_EOL;
    }
    exit(1);
}

echo "✅ Signature valide\n\n";

// 3. Parser différentes requêtes
$queries = [
    'user:create John john@example.com --admin --role=admin',
    'user:create Jane --admin tags:php,tags:laravel',
    'user:create Bob --role=editor'
];

foreach ($queries as $query) {
    echo "Requête: " . $query . PHP_EOL;
    
    // Valider la requête
    if (!$parser->isValid($signature, $query)) {
        $errors = $parser->getValidationErrors($signature, $query);
        echo "  ❌ Erreurs:\n";
        foreach ($errors as $error) {
            echo "    - " . $error . PHP_EOL;
        }
        continue;
    }
    
    // Parser la requête
    $result = $parser->parse($signature, $query);
    
    echo "  ✅ Parse réussi:\n";
    echo "    Name: " . ($result->required->get('name') ?? 'non fourni') . PHP_EOL;
    echo "    Email: " . ($result->default->get('email') ?? 'non fourni') . PHP_EOL;
    echo "    Admin: " . ($result->flags->get('admin') ? 'oui' : 'non') . PHP_EOL;
    echo "    Role: " . ($result->flags->get('role') ?? 'user') . PHP_EOL;
    
    $tags = $result->variadic->getAllValues();
    if (!empty($tags)) {
        echo "    Tags: " . implode(', ', $tags) . PHP_EOL;
    }
    
    echo PHP_EOL;
}

// 4. Extraire les éléments
$elements = $parser->extractSignatureElements($signature);
echo "Éléments de la signature:\n";
foreach ($elements as $element) {
    echo "  - " . $element . PHP_EOL;
}

// 5. Gérer les parsers personnalisés
class MyCustomParser implements ParserInterface
{
    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        // Logique personnalisée
    }
}

$parser->addParser(new MyCustomParser());
$parsers = $parser->getParsers();
echo "Parsers enregistrés: " . count($parsers) . PHP_EOL;
```

## Notes techniques

### Syntaxe de signature détaillée

```php
// Arguments requis - doivent être présents
{name}

// Arguments optionnels - peuvent être omis
{name?}

// Arguments avec valeur par défaut - utilisés si omis
{name=default}

// Flags booléens - présence = true, absence = false
{--flag}

// Flags avec valeur - doivent être spécifiés
{--flag=value}

// Arguments variadiques - peuvent apparaître plusieurs fois
{tags*}
```

### Validation des types

Le parser supporte la validation des types à l'avenir via des annotations :

```php
// Proposition future
{name:string}   // Doit être une chaîne
{age:int}       // Doit être un entier
{active:bool}   // Doit être un booléen
```

### Gestion des espaces

Les arguments avec espaces peuvent être entre guillemets :

```php
// Requête: user:create "John Doe"
// L'argument name sera "John Doe"
```

### Chaînage des méthodes

```php
$parser
    ->addParser(new CustomParser())
    ->parse($signature, $query);
```

### Extensibilité

Le service permet d'ajouter des parsers personnalisés pour des besoins spécifiques :

```php
// Parser pour des formats de date personnalisés
$parser->addParser(new DateParser());

// Parser pour des expressions régulières
$parser->addParser(new RegexParser());
```
---