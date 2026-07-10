# BuiltInDirectiveDiscovery - Référence Technique

## Description

Source de découverte des directives intégrées au package. Fournit les directives core qui sont livrées nativement avec la bibliothèque.

## Hiérarchie / Implémentations

```
DiscoverySourceInterface
    └── AbstractDiscovery
        └── BuiltInDirectiveDiscovery
```

## Rôle principal

`BuiltInDirectiveDiscovery` est la source de découverte la plus fondamentale. Elle permet de :

- Fournir les directives intégrées du package
- Servir de source de base pour le système de directives
- Hériter des fonctionnalités de suivi des problèmes via `AbstractDiscovery`
- Garantir la disponibilité des commandes essentielles (help, list, version, clean-logs, kernel:audit)

## Installation

```bash
composer require andydefer/laravel-directive
```

### Dépendances

- `DiscoverySourceInterface` - Interface de source de découverte
- `AbstractDiscovery` - Classe de base avec gestion des problèmes
- PHP 8.1+

## API / Méthodes publiques

### `discover(): array`

Retourne la liste des classes de directives intégrées.

**Retourne :** `array<int, class-string>` - Liste des FQCN des directives

**Exceptions :** Aucune

**Exemple :**
```php
$discovery = new BuiltInDirectiveDiscovery();
$directives = $discovery->discover();

foreach ($directives as $directive) {
    echo "Built-in: $directive\n";
}
// Built-in: AndyDefer\Directive\BuiltIn\ListDirective
// Built-in: AndyDefer\Directive\BuiltIn\HelpDirective
// Built-in: AndyDefer\Directive\BuiltIn\VersionDirective
// Built-in: AndyDefer\Directive\BuiltIn\CleanLogsDirective
// Built-in: AndyDefer\Directive\BuiltIn\KernelAuditDirective
```

---

### `getProblems(): ListCollection` (hérité de AbstractDiscovery)

Retourne la collection des problèmes rencontrés lors de la découverte.

**Retourne :** `ListCollection` - Collection des problèmes

**Exemple :**
```php
$discovery = new BuiltInDirectiveDiscovery();
$directives = $discovery->discover();
$problems = $discovery->getProblems();

if ($problems->isNotEmpty()) {
    foreach ($problems as $problem) {
        echo $problem->get('message') . "\n";
    }
}
```

---

### `clearProblems(): self` (hérité de AbstractDiscovery)

Efface tous les problèmes.

**Retourne :** `self` - Instance fluide

---

## Directives intégrées

### 1. ListDirective - Liste des directives
Affiche toutes les directives disponibles avec leurs descriptions et signatures.

**Signature :** `list`

**Alias :** `ls`, `-l`, `--list`

**Exemple :**
```bash
./bin/directive list
./bin/directive ls
./bin/directive -l
```

---

### 2. HelpDirective - Aide
Affiche l'aide détaillée d'une directive spécifique ou une aide générale.

**Signature :** `help`

**Alias :** `-h`, `--help`

**Exemple :**
```bash
./bin/directive help
./bin/directive help greet
./bin/directive -h
```

---

### 3. VersionDirective - Version
Affiche la version du package et des dépendances.

**Signature :** `version`

**Alias :** `-v`, `--version`

**Exemple :**
```bash
./bin/directive version
./bin/directive -v
```

---

### 4. CleanLogsDirective - Nettoyage des logs
Supprime les logs de directives datant de plus de X jours.

**Signature :** `clean-directive-logs {days=30} {--dry-run} {--verbose}`

**Alias :** `log-directive-clean`, `ldc`

**Arguments :**
- `days` - Nombre de jours à conserver (défaut: 30)

**Options :**
- `--dry-run` - Simulation sans suppression réelle
- `--verbose` - Affichage détaillé

**Exemple :**
```bash
./bin/directive clean-directive-logs
./bin/directive clean-directive-logs 7
./bin/directive clean-directive-logs 14 --dry-run
./bin/directive ldc --verbose
```

---

### 5. KernelAuditDirective - Audit du noyau
Affiche un rapport d'audit du système de découverte avec les métriques et les problèmes.

**Signature :** `kernel:audit {--verbose} {--format=table}`

**Alias :** `audit`

**Options :**
- `--verbose` - Affiche les détails des données contextuelles
- `--format` - Format de sortie (table ou list)

**Exemple :**
```bash
./bin/directive kernel:audit
./bin/directive kernel:audit --verbose
./bin/directive audit --format=list
```

---

## Cas d'utilisation

### Cas 1 : Utilisation directe dans un kernel

```php
<?php

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;

$kernel = DirectiveKernel::init($container);

// Le kernel utilise automatiquement BuiltInDirectiveDiscovery
// via DirectiveDiscoveryService
$kernel->run(['directive', 'help']);
$kernel->run(['directive', 'list']);
$kernel->run(['directive', 'version']);
$kernel->run(['directive', 'clean-directive-logs']);
$kernel->run(['directive', 'kernel:audit']);
```

### Cas 2 : Extension avec des directives personnalisées

```php
<?php

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;

class ExtendedBuiltInDiscovery extends BuiltInDirectiveDiscovery
{
    private array $extraDirectives = [
        MyCustomDirective::class,
        AnotherDirective::class,
    ];
    
    public function discover(): array
    {
        $builtIn = parent::discover();
        return array_merge($builtIn, $this->extraDirectives);
    }
}

$discovery = new ExtendedBuiltInDiscovery();
$allDirectives = $discovery->discover();
// Contient les 5 directives intégrées + les 2 personnalisées
```

### Cas 3 : Utilisation dans un service de découverte

```php
<?php

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;

$discoveryService = DirectiveDiscoveryService::init($container);
$builtInSource = new BuiltInDirectiveDiscovery();

foreach ($builtInSource->discover() as $fqcn) {
    $discoveryService->addDirective($fqcn);
}

$directives = $discoveryService->discover();
```

### Cas 4 : Vérification des directives intégrées

```php
<?php

$discovery = new BuiltInDirectiveDiscovery();
$directives = $discovery->discover();

$expected = [
    'AndyDefer\Directive\BuiltIn\ListDirective',
    'AndyDefer\Directive\BuiltIn\HelpDirective',
    'AndyDefer\Directive\BuiltIn\VersionDirective',
    'AndyDefer\Directive\BuiltIn\CleanLogsDirective',
    'AndyDefer\Directive\BuiltIn\KernelAuditDirective',
];

foreach ($expected as $expectedClass) {
    if (in_array($expectedClass, $directives, true)) {
        echo "✅ $expectedClass est disponible\n";
    } else {
        echo "❌ $expectedClass est manquant\n";
    }
}
```

### Cas 5 : Suppression conditionnelle des directives

```php
<?php

$discovery = new BuiltInDirectiveDiscovery();
$allDirectives = $discovery->discover();

// Supprimer une directive intégrée si elle n'est pas souhaitée
$excluded = ['AndyDefer\Directive\BuiltIn\CleanLogsDirective'];
$filtered = array_filter($allDirectives, function($class) use ($excluded) {
    return !in_array($class, $excluded, true);
});

echo "Directives intégrées (filtrées): " . count($filtered) . "\n";
// ListDirective, HelpDirective, VersionDirective, KernelAuditDirective
```

---

## Flux d'exécution

```
BuiltInDirectiveDiscovery::discover()
    ↓
Retourner le tableau $builtInDirectives
    ├── ListDirective::class
    ├── HelpDirective::class
    ├── VersionDirective::class
    ├── CleanLogsDirective::class
    └── KernelAuditDirective::class
    ↓
Utilisation dans DirectiveDiscoveryService
    ↓
Découverte des directives intégrées
    ↓
Ajout à la collection des directives disponibles
```

### Intégration dans la découverte globale

```
DirectiveDiscoveryService::discover()
    ↓
discoverBuiltInDirectives()
    ├── new BuiltInDirectiveDiscovery()
    ├── discover() → [ListDirective, HelpDirective, ...]
    ├── Récupérer les problèmes de la source
    └── addDirectiveFromFqcn() pour chaque classe
    ↓
discoverWorkspaceDirectives()
    ↓
discoverVendorDirectives()
    ↓
discoverCustomDirectives()
    ↓
Collection complète des directives
```

---

## Gestion des erreurs

Aucune exception n'est levée par cette classe. Les problèmes sont hérités de `AbstractDiscovery`.

| Situation | Comportement |
|-----------|--------------|
| Classe inexistante | Non applicable (classes définies) |
| Source inaccessible | Non applicable (source statique) |

---

## Intégration

### Avec DirectiveDiscoveryService

```php
$discoveryService = DirectiveDiscoveryService::init($container);

// Les directives intégrées sont automatiquement découvertes
// via BuiltInDirectiveDiscovery
$directives = $discoveryService->discover();

// Vérifier la présence d'une directive intégrée
$hasAudit = $directives->some(function($directive) {
    return $directive->class === KernelAuditDirective::class;
});
```

### Avec DirectiveKernel

```php
$kernel = DirectiveKernel::init($container);

// Les directives intégrées sont disponibles immédiatement
$kernel->run(['directive', 'help']);           // ✅
$kernel->run(['directive', 'list']);           // ✅
$kernel->run(['directive', 'version']);        // ✅
$kernel->run(['directive', 'clean-directive-logs']); // ✅
$kernel->run(['directive', 'kernel:audit']);   // ✅
```

### Personnalisation de l'ordre de découverte

```php
$discoveryService = DirectiveDiscoveryService::init($container);

// Ajouter les directives intégrées en premier
$builtIn = new BuiltInDirectiveDiscovery();
foreach ($builtIn->discover() as $class) {
    $discoveryService->addDirective($class);
}

// Puis ajouter les autres sources
$discoveryService
    ->addSource(__DIR__ . '/src/Directives')
    ->enableAutoDiscovery();

$directives = $discoveryService->discover();
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `discover()` | O(1) | Retourne un tableau fixe de 5 éléments |
| Intégration dans le discovery | O(1) | Ajout de 5 classes |

**Optimisations :**
- Le tableau est défini statiquement (pas de calcul)
- Pas d'opérations I/O
- Pas d'analyse AST

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

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Enums\ExitCode;

// 1. Création des services
$container = DirectiveContainer::create(__DIR__);
$kernel = DirectiveKernel::init($container);

// 2. Vérification des directives intégrées
echo "=== Directives intégrées ===\n";
$builtInDiscovery = new BuiltInDirectiveDiscovery();
$builtInDirectives = $builtInDiscovery->discover();

foreach ($builtInDirectives as $class) {
    $parts = explode('\\', $class);
    $shortName = end($parts);
    echo "✅ $shortName\n";
}
echo "\n";

// 3. Test de chaque directive intégrée
echo "=== Test des directives intégrées ===\n";

// 3a. Help
echo "\n--- Help ---\n";
$kernel->run(['directive', 'help']);

// 3b. List
echo "\n--- List (short) ---\n";
$kernel->run(['directive', 'list']);

// 3c. Version
echo "\n--- Version ---\n";
$kernel->run(['directive', 'version']);

// 3d. Clean Logs (dry-run)
echo "\n--- Clean Logs (dry-run) ---\n";
$kernel->run(['directive', 'clean-directive-logs', '--dry-run']);

// 3e. Kernel Audit
echo "\n--- Kernel Audit ---\n";
$kernel->run(['directive', 'kernel:audit']);

// 4. Découverte via le service
echo "\n=== Découverte via DirectiveDiscoveryService ===\n";
$discoveryService = DirectiveDiscoveryService::init($container);
$allDirectives = $discoveryService->discover();

$builtInCount = 0;
foreach ($allDirectives as $directive) {
    $isBuiltIn = in_array($directive->class, $builtInDirectives, true);
    if ($isBuiltIn) {
        $builtInCount++;
        $shortName = array_slice(explode('\\', $directive->class), -1)[0];
        echo "  - $shortName (intégrée)\n";
    }
}

echo "\nTotal directives intégrées: $builtInCount\n";

// 5. Comparaison avec les directives personnalisées
echo "\n=== Directives personnalisées ===\n";

// Ajouter une directive personnalisée
$discoveryService->addDirective(MyCustomDirective::class);

// Redécouvrir
$allDirectives = $discoveryService->discover();
$totalCount = $allDirectives->count();

echo "Total directives: $totalCount\n";
echo "Directives intégrées: $builtInCount\n";
echo "Directives personnalisées: " . ($totalCount - $builtInCount) . "\n";

// 6. Vérification des fonctionnalités
echo "\n=== Vérification des fonctionnalités ===\n";
$commands = ['help', 'list', 'version', 'clean-directive-logs', 'kernel:audit'];

foreach ($commands as $command) {
    $result = $kernel->run(['directive', $command]);
    $status = $result === ExitCode::SUCCESS ? '✅' : '❌';
    echo "$status $command\n";
}

// 7. Audit avec mode verbose
echo "\n=== Audit en mode verbose ===\n";
$kernel->verbose(true);
$kernel->run(['directive', 'kernel:audit', '--verbose']);
$kernel->verbose(false);

// 8. Création d'une source de découverte personnalisée
echo "\n=== Création d'une source personnalisée ===\n";

class ExtendedBuiltInDiscovery extends BuiltInDirectiveDiscovery
{
    private array $extra = [
        'App\\Directives\\CustomDirective',
        'App\\Directives\\AdminDirective',
    ];
    
    public function discover(): array
    {
        return array_merge(parent::discover(), $this->extra);
    }
}

$extended = new ExtendedBuiltInDiscovery();
$all = $extended->discover();

echo "Directives de la source étendue:\n";
foreach ($all as $class) {
    $shortName = array_slice(explode('\\', $class), -1)[0];
    $type = in_array($class, $builtInDirectives, true) ? 'intégrée' : 'personnalisée';
    echo "  - $shortName ($type)\n";
}
```

## Voir aussi

- `DiscoverySourceInterface` - Interface de source de découverte
- `AbstractDiscovery` - Classe de base avec gestion des problèmes
- `DirectiveDiscoveryService` - Service de découverte
- `ListDirective` - Directive de listing
- `HelpDirective` - Directive d'aide
- `VersionDirective` - Directive de version
- `CleanLogsDirective` - Directive de nettoyage des logs
- `KernelAuditDirective` - Directive d'audit du noyau