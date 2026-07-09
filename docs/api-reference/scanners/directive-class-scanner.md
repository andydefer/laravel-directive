# DirectiveClassScanner - Référence Technique

## Description

Scanner de classes de directives utilisant l'analyse AST (Abstract Syntax Tree). Détecte les classes qui étendent `AbstractDirective` en analysant la structure syntaxique des fichiers PHP.

## Hiérarchie / Implémentations

```
DirectiveScannerInterface
    └── DirectiveClassScanner
        └── NodeVisitor (analyse AST)
```

## Rôle principal

`DirectiveClassScanner` est le moteur de découverte des classes de directives. Il permet de :

- Scanner récursivement des répertoires à la recherche de classes
- Analyser l'AST des fichiers PHP pour identifier les directives
- Gérer les alias d'import (`use`) pour résoudre les FQCN
- Ignorer les classes abstraites et les classes sans namespace
- Traverser l'arborescence avec une profondeur configurable

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `FileSystemInterface` - Opérations sur le système de fichiers
- `Parser` (PhpParser) - Analyse syntaxique PHP
- `NodeTraverser` - Parcours de l'AST
- PHP 8.1+

## API / Méthodes publiques

### `__construct(FileSystemInterface $fileSystem, Parser $parser)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fileSystem` | `FileSystemInterface` | Service de système de fichiers |
| `$parser` | `Parser` | Parseur PHP (PhpParser) |

**Retourne :** `void`

**Exemple :**
```php
$fileSystem = new FileSystemService();
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$scanner = new DirectiveClassScanner($fileSystem, $parser);
```

---

### `scan(string $directory, int $maxDepth = 3): array`

Scanne un répertoire à la recherche de classes de directives.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directory` | `string` | Chemin du répertoire à scanner |
| `$maxDepth` | `int` | Profondeur maximale de scan (défaut: 3) |

**Retourne :** `array<int, string>` - Liste des FQCN (Fully Qualified Class Names)

**Exceptions :** Aucune (les erreurs de parsing sont ignorées)

**Exemple :**
```php
$classes = $scanner->scan('/path/to/project/src', 5);

foreach ($classes as $class) {
    echo "Found directive: $class\n";
}
// App\Directives\GreetDirective
// App\Directives\HelpDirective
// App\Commands\AdminDirective
```

---

## Cas d'utilisation

### Cas 1 : Scan standard d'un projet

```php
<?php

use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;
use PhpParser\ParserFactory;

$fileSystem = new FileSystemService();
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$scanner = new DirectiveClassScanner($fileSystem, $parser);

// Scanner le dossier src avec profondeur 4
$directives = $scanner->scan(__DIR__ . '/src', 4);

echo "Directives trouvées: " . count($directives) . "\n";
foreach ($directives as $class) {
    echo "- $class\n";
}
```

### Cas 2 : Scan de plusieurs sources

```php
<?php

$sources = [
    __DIR__ . '/src/Commands',
    __DIR__ . '/src/Directives',
    __DIR__ . '/vendor/andydefer/directive/src/BuiltIn',
];

$allDirectives = [];

foreach ($sources as $source) {
    $classes = $scanner->scan($source, 2);
    $allDirectives = array_merge($allDirectives, $classes);
}

echo "Total directives: " . count($allDirectives) . "\n";
```

### Cas 3 : Intégration dans un service de découverte

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

class CustomDirectiveDiscovery extends DirectiveDiscoveryService
{
    private DirectiveClassScanner $scanner;
    
    public function discoverWorkspaceDirectives(): void
    {
        $config = $this->getConfig();
        $basePath = $config->basePath();
        
        // Scanner avec le scanner AST
        $fqcns = $this->scanner->scan($basePath . '/src', 3);
        
        foreach ($fqcns as $fqcn) {
            $this->addDirectiveFromFqcn($fqcn);
        }
    }
}
```

### Cas 4 : Analyse des imports et alias

```php
<?php

// Exemple de fichier PHP avec imports
// src/Directives/GreetDirective.php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective as BaseDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class GreetDirective extends BaseDirective
{
    // ...
}

// Le scanner résout correctement l'alias "BaseDirective"
// et détecte que GreetDirective étend AbstractDirective
```

### Cas 5 : Filtrage des classes abstraites

```php
<?php

// src/Directives/AbstractBaseDirective.php
namespace App\Directives;

abstract class AbstractBaseDirective extends AbstractDirective
{
    // Classe abstraite - IGNORÉE
}

// src/Directives/ConcreteDirective.php
final class ConcreteDirective extends AbstractBaseDirective
{
    // Classe concrète - DÉTECTÉE
}

// Résultat du scan:
// ['App\Directives\ConcreteDirective']
```

---

## Flux d'exécution

```
scan($directory, $maxDepth)
    ↓
Vérifier si le répertoire existe
    ├── Non → retourner []
    └── Oui → scanDirectory()
        ↓
scanDirectory($directory, &$fqcns, $depth, $maxDepth)
    ├── depth > maxDepth → retourner
    ├── Parcourir les fichiers *.php
    │   ├── Pour chaque fichier
    │   │   ├── Lire le contenu
    │   │   └── analyzeFile($content)
    │   │       ├── parser->parse($content) → AST
    │   │       ├── Visitor parcourt l'AST
    │   │       │   ├── Namespace_ → enregistrer
    │   │       │   ├── Use_ → enregistrer les alias
    │   │       │   └── Class_ → vérifier l'extension
    │   │       │       ├── Classe abstraite → ignorer
    │   │       │       ├── Pas de namespace → ignorer
    │   │       │       ├── Étend AbstractDirective → enregistrer
    │   │       │       └── Étend un alias → résoudre
    │   │       └── Retourner les FQCN trouvés
    │   └── Ajouter les classes à $fqcns
    ├── Parcourir les sous-répertoires
    │   └── scanDirectory($subDir, $fqcns, depth+1)
    └── Retourner $fqcns
```

### Analyse AST détaillée

```
analyzeFile($content)
    ↓
parser->parse($content)
    ├── Succès → AST
    └── Erreur → retourner []
    ↓
Créer un NodeVisitor personnalisé
    ↓
Traverser l'AST
    ├── Namespace_ → $currentNamespace = 'App\Directives'
    ├── Use_ → $aliases['BaseDirective'] = 'AndyDefer\Directive\AbstractDirective'
    └── Class_ → $node->extends? 
        ├── Oui → $parentName = $node->extends->toString()
        │   ├── 'BaseDirective' → résoudre avec $aliases
        │   └── 'AndyDefer\Directive\AbstractDirective' → direct
        ├── Vérifier si parent est AbstractDirective
        ├── Vérifier si classe abstraite
        ├── Vérifier si namespace existe
        └── Ajouter FQCN
    ↓
Retourner la liste des classes
```

---

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Répertoire inexistant | Retourne un tableau vide |
| Fichier PHP invalide | Ignoré (silencieusement) |
| Erreur de parsing | Ignorée (silencieusement) |
| Classe sans namespace | Ignorée (silencieusement) |
| Classe abstraite | Ignorée |
| Fichier non lisible | Ignoré (silencieusement) |

**Aucune exception n'est levée.** Toutes les erreurs sont gérées silencieusement.

---

## Intégration

### Avec le service de découverte

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;

class VendorDirectiveDiscovery
{
    public function __construct(
        private DirectiveClassScanner $scanner,
        private ComposerReaderInterface $composerReader,
    ) {}
    
    public function discover(): array
    {
        $packages = $this->composerReader->getPackageNames();
        $directives = [];
        
        foreach ($packages as $package) {
            $vendorDir = $this->composerReader->getVendorDir();
            $path = $vendorDir . '/' . $package . '/src';
            
            if (is_dir($path)) {
                $classes = $this->scanner->scan($path, 2);
                $directives = array_merge($directives, $classes);
            }
        }
        
        return $directives;
    }
}
```

### Avec Laravel

```php
<?php

namespace App\Providers;

use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use Illuminate\Support\ServiceProvider;
use PhpParser\ParserFactory;

class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DirectiveClassScanner::class, function ($app) {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            return new DirectiveClassScanner(
                $app->make(FileSystemInterface::class),
                $parser
            );
        });
    }
}
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `scan()` | O(n × m) | n = fichiers, m = profondeur |
| `scanDirectory()` | O(f × a) | f = fichiers, a = analyse AST |
| `analyzeFile()` | O(c) | c = complexité du code |

**Optimisations :**
- Parse seulement les fichiers `.php`
- Ignore les erreurs de parsing rapidement
- Parcours récursif avec limite de profondeur
- Pas d'analyse des dépendances

**Mémoire :**
- L'AST est chargé pour chaque fichier (puis libéré)
- Les FQCN sont stockés dans un tableau simple
- Pas de cache permanent

---

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.4 | ✅ Complet | Support total |
| PHP 8.3 | ✅ Complet | Support total |
| PHP 8.2 | ✅ Complet | Support total |
| PHP 8.1 | ✅ Complet | Support total |

**Dépendances :**
- `nikic/php-parser` ^5.8

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;
use PhpParser\ParserFactory;

// 1. Création du scanner
$fileSystem = new FileSystemService();
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$scanner = new DirectiveClassScanner($fileSystem, $parser);

// 2. Configuration des sources
$sources = [
    'builtin' => __DIR__ . '/vendor/andydefer/directive/src/BuiltIn',
    'workspace' => __DIR__ . '/src/Directives',
    'vendor' => __DIR__ . '/vendor',
];

// 3. Scan des sources
echo "=== Scan des directives ===\n\n";

foreach ($sources as $name => $path) {
    echo "Source: $name\n";
    echo "Chemin: $path\n";
    
    if (!is_dir($path)) {
        echo "⚠️  Répertoire inexistant\n\n";
        continue;
    }
    
    $classes = $scanner->scan($path, 3);
    
    echo "Directives trouvées: " . count($classes) . "\n";
    
    foreach ($classes as $class) {
        echo "  - $class\n";
    }
    
    echo "\n";
}

// 4. Analyse détaillée des classes trouvées
$allClasses = [];
foreach ($sources as $path) {
    if (is_dir($path)) {
        $classes = $scanner->scan($path, 3);
        $allClasses = array_merge($allClasses, $classes);
    }
}

echo "=== Analyse détaillée ===\n";
echo "Total des classes: " . count($allClasses) . "\n\n";

$directivesByNamespace = [];
foreach ($allClasses as $class) {
    $parts = explode('\\', $class);
    $namespace = implode('\\', array_slice($parts, 0, -1));
    $directivesByNamespace[$namespace][] = $class;
}

// Afficher par namespace
foreach ($directivesByNamespace as $namespace => $classes) {
    echo "Namespace: $namespace\n";
    foreach ($classes as $class) {
        $shortName = array_slice(explode('\\', $class), -1)[0];
        echo "  - $shortName\n";
    }
    echo "\n";
}

// 5. Statistiques
echo "=== Statistiques ===\n";
echo "Total: " . count($allClasses) . " classes\n";
echo "Namespaces: " . count($directivesByNamespace) . "\n";

// 6. Vérification des classes abstraites
$abstractClasses = [];
$concreteClasses = [];

foreach ($allClasses as $class) {
    $reflection = new ReflectionClass($class);
    if ($reflection->isAbstract()) {
        $abstractClasses[] = $class;
    } else {
        $concreteClasses[] = $class;
    }
}

echo "\nClasses abstraites: " . count($abstractClasses) . "\n";
echo "Classes concrètes: " . count($concreteClasses) . "\n";

// 7. Exemple de résultat
echo "\n=== Exemples de directives ===\n";
$sample = array_slice($concreteClasses, 0, 5);
foreach ($sample as $class) {
    $parts = explode('\\', $class);
    $shortName = end($parts);
    echo "- $shortName (classe concrète)\n";
}

// 8. Test avec un fichier spécifique (analyse AST)
$testFile = __DIR__ . '/example.php';
if (file_exists($testFile)) {
    echo "\n=== Analyse d'un fichier spécifique ===\n";
    $content = file_get_contents($testFile);
    $classes = $scanner->analyzeFile($content);
    
    echo "Classes dans " . basename($testFile) . ":\n";
    foreach ($classes as $class) {
        echo "  - $class\n";
    }
}
```

## Voir aussi

- `DirectiveDiscoveryService` - Service de découverte
- `DirectiveClassScanner` - Interface du scanner
- `PhpParser` - Analyse AST
- `FileSystemService` - Service de système de fichiers
- `AbstractDirective` - Classe de base des directives