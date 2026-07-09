# DirectiveDiscoveryService - Référence Technique

## Description

Service d'orchestration qui découvre et gère les classes de directives provenant de multiples sources. Il coordonne la découverte depuis les sources intégrées, le workspace, les packages vendors et les sources personnalisées.

## Hiérarchie / Implémentations

```
DirectiveDiscoveryService (final)
```

## Rôle principal

Agir comme un orchestrateur central qui :
1. Agrège les directives de toutes les sources
2. Filtre les signatures réservées
3. Déduplique les directives
4. Fournit une collection unifiée de toutes les directives disponibles

## Installation

### Configuration dans le conteneur

```php
// Dans le service provider
$this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
    return new DirectiveDiscoveryService(
        $app->make(BuiltInDirectiveDiscovery::class),
        $app->make(WorkspaceDirectiveDiscovery::class),
        $app->make(VendorDirectiveDiscovery::class),
        $app->make(DirectiveParserInterface::class),
        $app->make(DirectiveScannerInterface::class),
        $app->make(FileSystemInterface::class),
        $app->make(DirectiveConfigInterface::class),
        3 // maxDepth
    );
});
```

## API / Méthodes publiques

### `addSource(string $directory): self`

Ajoute un répertoire source personnalisé à scanner.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directory` | `string` | Chemin du répertoire à scanner |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery->addSource('app/CustomDirectives');
```

---

### `addSources(array $directories): self`

Ajoute plusieurs répertoires sources personnalisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directories` | `array<int, string>` | Liste des chemins à scanner |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery->addSources([
    'app/CustomDirectives',
    'modules/Admin/Directives',
    'packages/Acme/Directives',
]);
```

---

### `discover(): DirectiveMetadataCollection`

Découvre toutes les directives disponibles depuis toutes les sources.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `DirectiveMetadataCollection` - Collection des directives découvertes

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$directives = $discovery->discover();

foreach ($directives as $directive) {
    echo $directive->signature . ': ' . $directive->description . PHP_EOL;
}
```

---

### `addReservedSignature(string $signature): self`

Ajoute une signature à la liste des réservées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La signature à réserver |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery->addReservedSignature('my-command');
```

---

### `removeReservedSignature(string $signature): self`

Retire une signature de la liste des réservées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La signature à retirer |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery->removeReservedSignature('help');
```

---

### `getReservedSignatures(): array`

Récupère la liste des signatures réservées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des signatures réservées

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$reserved = $discovery->getReservedSignatures();
// ['-h', '--help', '-v', '--version', ...]
```

## Cas d'utilisation

### Cas 1 : Découverte des directives dans une application

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

// Dans un contrôleur ou un service
$discovery = app(DirectiveDiscoveryService::class);
$directives = $discovery->discover();

foreach ($directives as $directive) {
    echo "Signature: " . $directive->signature . PHP_EOL;
    echo "Classe: " . $directive->class . PHP_EOL;
    echo "Description: " . $directive->description . PHP_EOL;
    
    if ($directive->aliases->isNotEmpty()) {
        echo "Alias: " . $directive->aliases->join(', ') . PHP_EOL;
    }
    echo PHP_EOL;
}
```

### Cas 2 : Ajout de sources dynamiques

```php
<?php

class ModuleServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $discovery = $this->app->make(DirectiveDiscoveryService::class);
        
        // Ajouter les directives des modules actifs
        foreach ($this->getActiveModules() as $module) {
            $path = base_path("modules/{$module}/Directives");
            
            if (is_dir($path)) {
                $discovery->addSource($path);
            }
        }
    }
    
    private function getActiveModules(): array
    {
        return ['Admin', 'Api', 'Blog'];
    }
}
```

### Cas 3 : Gestion des signatures réservées

```php
<?php

$discovery = app(DirectiveDiscoveryService::class);

// Ajouter une signature réservée
$discovery->addReservedSignature('import');

// Retirer une signature réservée
$discovery->removeReservedSignature('version');

// Voir les signatures réservées
$reserved = $discovery->getReservedSignatures();
```

### Cas 4 : Tests de découverte

```php
<?php

class DirectiveDiscoveryTest extends TestCase
{
    public function test_discover_directives()
    {
        $discovery = $this->app->make(DirectiveDiscoveryService::class);
        
        $directives = $discovery->discover();
        
        $this->assertGreaterThan(0, $directives->count());
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $directives);
        
        // Vérifier que les directives intégrées sont présentes
        $signatures = $directives->pluck('signature')->toArray();
        $this->assertContains('list', $signatures);
        $this->assertContains('help', $signatures);
        $this->assertContains('version', $signatures);
    }
}
```

## Flux d'exécution

```
DirectiveDiscoveryService::discover()
    │
    ├── discoverBuiltInDirectives()
    │   └── $builtInSource->discover()
    │       └── addDirective($fqcn, true)  ← force = true (prioritaire)
    │
    ├── discoverWorkspaceDirectives()
    │   └── $workspaceSource->discover()
    │       └── addDirective($fqcn, false)
    │
    ├── discoverVendorDirectives()
    │   └── $vendorSource->discover()
    │       └── addDirective($fqcn, false)
    │
    ├── discoverCustomDirectives()
    │   ├── foreach($customSources)
    │   │   ├── $scanner->scan($directory)
    │   │   └── addDirective($fqcn, false)
    │   └──
    │
    └── return $this->collection->uniqueByClass()
        └── Déduplication par nom de classe
```

## Ordre de découverte

| Ordre | Source | Force | Description |
|-------|--------|-------|-------------|
| 1 | `BuiltInDirectiveDiscovery` | ✅ Force | Directives intégrées (prioritaires) |
| 2 | `WorkspaceDirectiveDiscovery` | ❌ | Directives du projet |
| 3 | `VendorDirectiveDiscovery` | ❌ | Directives des packages vendors |
| 4 | `CustomSources` | ❌ | Sources personnalisées |

### Règle de force

- **Force = true** : La directive est ajoutée même si sa signature est réservée
- **Force = false** : La directive est ignorée si sa signature est réservée

## Filtrage des directives

### 1. Validation de la classe

```php
private function isValidDirectiveClass(ReflectionClass $reflection): bool
{
    if ($reflection->isAbstract()) {
        return false; // ❌ Les classes abstraites sont ignorées
    }

    return $reflection->isSubclassOf(AbstractDirective::class); // ✅ Doit étendre AbstractDirective
}
```

### 2. Vérification des signatures réservées

```php
private function isReservedSignature(string $signature): bool
{
    $parsed = $this->parser->parse($signature, '');
    $commandName = $parsed->source;
    
    return in_array($commandName, $this->config->getReservedSignatures(), true);
}
```

### 3. Déduplication

Les directives sont dédupliquées par nom de classe pour éviter les doublons :

```php
return $this->collection->uniqueByClass();
```

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Répertoire personnalisé inexistant | Ignoré silencieusement | - |
| Classe abstraite | Ignorée (non ajoutée) | - |
| Classe ne respectant pas `AbstractDirective` | Ignorée (non ajoutée) | - |
| Signature réservée | Ignorée (non ajoutée) | - |

### Signatures réservées par défaut

```php
const DEFAULT_RESERVED_SIGNATURES = [
    '-h',
    '--help',
    '-v',
    '--version',
    '-l',
    '--list',
    'help',
    'list',
    'version',
];
```

## Intégration

Le `DirectiveDiscoveryService` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `DiscoverySourceInterface` | Sources de découverte (BuiltIn, Workspace, Vendor) |
| `DirectiveParserInterface` | Parsing des signatures pour validation |
| `DirectiveScannerInterface` | Scan des répertoires personnalisés |
| `FileSystemInterface` | Vérification des répertoires |
| `DirectiveConfigInterface` | Configuration et signatures réservées |
| `DirectiveMetadataCollection` | Collection des directives découvertes |

### Utilisation par d'autres composants

```php
// Dans DirectiveKernel
class DirectiveKernel
{
    public function run(array $argv): ExitCode
    {
        $directives = $this->discovery->discover();
        // Utiliser la collection pour trouver la directive appropriée
    }
}
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(n × m) | n = sources, m = directives par source |
| Temps typique | 200-800ms | Première découverte |
| Mémoire | 2-5 MB | Dépend du nombre de directives |
| Cache | ❌ Non | Recalcul à chaque appel |

### Facteurs de performance

1. **Nombre de sources** : Plus il y a de sources, plus la découverte est lente
2. **Nombre de directives** : Plus il y a de directives, plus la collection est grande
3. **Profondeur de scan** : Scan plus profond → plus de fichiers → plus lent
4. **Parsing** : Chaque directive est parsée pour validation

### Optimisations

```php
class DirectiveDiscoveryService
{
    private ?DirectiveMetadataCollection $cachedDirectives = null;
    
    public function discover(): DirectiveMetadataCollection
    {
        if ($this->cachedDirectives !== null) {
            return $this->cachedDirectives;
        }
        
        // ... découverte ...
        
        $this->cachedDirectives = $this->collection->uniqueByClass();
        return $this->cachedDirectives;
    }
    
    public function clearCache(): void
    {
        $this->cachedDirectives = null;
    }
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| Laravel 9.x | ✅ Complet | - |
| Laravel 10.x | ✅ Complet | - |
| Laravel 11.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

// Dans un service provider
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return new DirectiveDiscoveryService(
                $app->make(BuiltInDirectiveDiscovery::class),
                $app->make(WorkspaceDirectiveDiscovery::class),
                $app->make(VendorDirectiveDiscovery::class),
                $app->make(DirectiveParserInterface::class),
                $app->make(DirectiveScannerInterface::class),
                $app->make(FileSystemInterface::class),
                $app->make(DirectiveConfigInterface::class),
                3
            );
        });
    }
}

// Utilisation dans un contrôleur
class DirectiveController extends Controller
{
    public function index(DirectiveDiscoveryService $discovery)
    {
        // Ajouter des sources personnalisées
        $discovery->addSources([
            base_path('app/CustomDirectives'),
            base_path('modules/Admin/Directives'),
        ]);
        
        // Ajouter une signature réservée
        $discovery->addReservedSignature('import');
        
        // Découvrir les directives
        $directives = $discovery->discover();
        
        return response()->json([
            'total' => $directives->count(),
            'directives' => $directives->map(function ($directive) {
                return [
                    'signature' => $directive->signature,
                    'description' => $directive->description,
                    'class' => $directive->class,
                    'aliases' => $directive->aliases->toArray(),
                ];
            })->toArray(),
        ]);
    }
}

// Vérification des signatures réservées
$discovery = app(DirectiveDiscoveryService::class);
$reserved = $discovery->getReservedSignatures();

echo "Signatures réservées:\n";
foreach ($reserved as $signature) {
    echo "- {$signature}\n";
}

// Retirer une signature réservée
if (in_array('version', $reserved, true)) {
    $discovery->removeReservedSignature('version');
    echo "Signature 'version' retirée des réservées\n";
}
```

## Notes techniques

### Stratégie de force

Les directives intégrées sont marquées avec `force = true` pour garantir leur présence :

```php
private function discoverBuiltInDirectives(): void
{
    $fqcns = $this->builtInSource->discover();
    
    foreach ($fqcns as $fqcn) {
        $this->addDirective($fqcn, true); // ✅ Force = true
    }
}
```

### Déduplication intelligente

La collection utilise `uniqueByClass()` pour éviter les doublons par nom de classe, même si la même directive est découverte depuis plusieurs sources.

### Validation des signatures

Les signatures sont parsées et validées avant d'être ajoutées à la collection :

```php
private function isReservedSignature(string $signature): bool
{
    $parsed = $this->parser->parse($signature, '');
    $commandName = $parsed->source;
    
    return in_array($commandName, $this->config->getReservedSignatures(), true);
}
```

### Points d'extension

Le service peut être étendu via :
1. **Nouvelles sources** : Ajout via `addSource()` et `addSources()`
2. **Signatures réservées** : Gestion via `addReservedSignature()` et `removeReservedSignature()`
3. **Sources personnalisées** : Implémentation de `DiscoverySourceInterface`
---