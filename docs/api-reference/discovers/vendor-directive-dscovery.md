# VendorDirectiveDiscovery - Référence Technique

## Description

Source de découverte qui scanne les packages Composer installés pour trouver des directives. Elle examine les chemins PSR-4 d'autoloading et les fichiers de configuration personnalisés des packages vendors.

## Hiérarchie / Implémentations

```
DiscoverySourceInterface
    └── VendorDirectiveDiscovery (final)
```

## Rôle principal

Permettre aux packages tiers de fournir leurs propres directives en les découvrant automatiquement. Cela rend l'écosystème Laravel Directive extensible : n'importe quel package Composer peut inclure des directives qui seront automatiquement disponibles.

## Installation

Cette classe est utilisée automatiquement par le service de découverte. Aucune configuration manuelle n'est nécessaire.

```php
// Le service provider l'enregistre automatiquement
$this->app->singleton(VendorDirectiveDiscovery::class, function ($app) {
    return new VendorDirectiveDiscovery(
        $app->make(ComposerReaderInterface::class),
        $app->make(DependencyResolverInterface::class),
        $app->make(FileSystemInterface::class),
        $app->make(DirectiveScannerInterface::class)
    );
});
```

## API / Méthodes publiques

### `discover(): array`

Découvre toutes les directives présentes dans les packages Composer installés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des noms de classes qualifiés (FQCN)

**Exceptions :** Aucune (les erreurs sont silencieusement ignorées)

**Exemple :**
```php
<?php

use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;

$discovery = $app->make(VendorDirectiveDiscovery::class);
$directives = $discovery->discover();

// Retourne les classes trouvées dans les packages vendors
// Exemple: ['Vendor\Package\Directives\MyDirective']
```

## Cas d'utilisation

### Cas 1 : Fournir des directives dans un package vendor

```php
// Dans un package vendor (ex: vendor/mon-package/composer.json)
{
    "autoload": {
        "psr-4": {
            "MonPackage\\": "src/"
        }
    }
}

// Structure du package
// vendor/mon-package/
//   src/
//     Directives/
//       MaDirective.php

// La directive sera automatiquement découverte
class MaDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'mon-package:commande';
    }
}
```

### Cas 2 : Configuration personnalisée dans un package vendor

```php
// vendor/mon-package/config/directive.php
<?php

return [
    'custom_sources' => [
        'src/Commands',     // Dossier supplémentaire à scanner
    ],
];

// La classe sera découverte
// vendor/mon-package/src/Commands/MaCommande.php
```

### Cas 3 : Utilisation dans un script d'analyse

```php
<?php

$discovery = $app->make(VendorDirectiveDiscovery::class);
$directives = $discovery->discover();

echo "Directives trouvées dans les vendors :" . PHP_EOL;

foreach ($directives as $fqcn) {
    $reflection = new ReflectionClass($fqcn);
    $instance = $reflection->newInstanceWithoutConstructor();
    echo "- " . $instance->getSignature() . " (" . $fqcn . ")" . PHP_EOL;
}
```

## Flux d'exécution

```
VendorDirectiveDiscovery::discover()
    │
    ├── $this->dependencyResolver->getFlatDependencies()
    │   └── Retourne la liste des packages installés
    │
    └── foreach($packages)
        │
        └── scanPackage($package)
            │
            ├── getPackagePath()
            │   └── /vendor/{package}
            │
            ├── scanAutoloadPaths()
            │   ├── readComposerJson()
            │   ├── Extrait les chemins PSR-4
            │   └── Scan /{path}/Directives/
            │
            └── scanCustomSources()
                ├── Vérifie config/directive.php
                ├── extractCustomSources()
                └── Scan chaque source personnalisée
```

## Structure de recherche

### 1. Chemins PSR-4

La classe recherche automatiquement dans les sous-dossiers `Directives` de chaque chemin PSR-4.

```php
// Exemple : Package "laravel/framework"
{
    "autoload": {
        "psr-4": {
            "Illuminate\\": "src/"
        }
    }
}

// Scan : vendor/laravel/framework/src/Directives/
```

### 2. Configuration personnalisée

Un package peut définir des sources supplémentaires via `config/directive.php` :

```php
// vendor/mon-package/config/directive.php
<?php

return [
    'custom_sources' => [
        'src/Commands',           // Relatif au package
        'src/Console/Commands',   // Autre dossier
    ],
];
```

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Package introuvable | Ignoré silencieusement | - |
| composer.json manquant | Ignoré silencieusement | - |
| JSON invalide | Ignoré silencieusement | - |
| Fichier de config inexistant | Ignoré silencieusement | - |
| Erreur de lecture fichier | Ignoré silencieusement | - |
| Erreur d'extraction des sources | Ignoré silencieusement | - |

⚠️ **Important** : Cette classe utilise `require` pour charger les fichiers de configuration. Assurez-vous que les packages tiers sont dignes de confiance.

## Intégration

La classe `VendorDirectiveDiscovery` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `ComposerReaderInterface` | Lecture du composer.json du projet |
| `DependencyResolverInterface` | Résolution des dépendances |
| `FileSystemInterface` | Opérations sur le système de fichiers |
| `DirectiveScannerInterface` | Scan des classes PHP |
| `DirectiveDiscoveryService` | Orchestration de la découverte |

### Ordre dans le processus de découverte

```
1. BuiltInDirectiveDiscovery      (prioritaire)
2. WorkspaceDirectiveDiscovery    (projet)
3. VendorDirectiveDiscovery       (packages)  ← Vous êtes ici
4. CustomSources                  (personnalisées)
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(n × m) | n = packages, m = fichiers par package |
| Temps typique | 200-500ms | Pour 10-20 packages avec scan modéré |
| Mémoire | 2-5 MB | Dépend du nombre de packages |
| Cache | Non | Recommandé de mettre en cache les résultats |

### Optimisations possibles

```php
// Ajout d'un cache pour les résultats
class VendorDirectiveDiscovery
{
    private ?array $cache = null;
    
    public function discover(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        
        $this->cache = $this->doDiscover();
        return $this->cache;
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
| Windows | ✅ Complet | Utilise `DIRECTORY_SEPARATOR` |
| Unix/Linux | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;

// Construire les dépendances
$fileSystem = new FileSystemService();
$config = $app->make(DirectiveConfigInterface::class);
$composerReader = new ComposerReaderService($config, $fileSystem);
$dependencyResolver = new DependencyResolverService($composerReader, $fileSystem);
$scanner = new DirectiveClassScanner($fileSystem, $parser);

// Créer le discovery
$discovery = new VendorDirectiveDiscovery(
    $composerReader,
    $dependencyResolver,
    $fileSystem,
    $scanner
);

// Découvrir les directives des vendors
$vendorDirectives = $discovery->discover();

// Analyser les résultats
echo "Directives trouvées : " . count($vendorDirectives) . PHP_EOL;

foreach ($vendorDirectives as $fqcn) {
    echo "- " . $fqcn . PHP_EOL;
}

// Pour voir d'où viennent les directives
// Utiliser le résolveur de dépendances
$dependencies = $dependencyResolver->getFlatDependencies();

foreach ($dependencies as $package) {
    echo "Package: " . $package . PHP_EOL;
    // Les directives de ce package sont incluses dans le résultat
}
```

## Notes de sécurité

⚠️ **Attention** : Cette classe utilise `require` pour charger les fichiers `config/directive.php` des packages vendors. Cela signifie que tout code dans ces fichiers sera exécuté. Bien que cela soit nécessaire pour la flexibilité, cela peut présenter un risque de sécurité si un package malveillant est installé.

### Bonnes pratiques

1. **Vérifier les packages** : N'installez que des packages de sources fiables
2. **Audit de sécurité** : Utilisez `composer audit` pour vérifier les vulnérabilités
3. **Environnement de développement** : Testez les packages dans un environnement isolé
4. **Contrôle des versions** : Utilisez des versions stables et vérifiées

### Alternatives sécurisées

```php
// Si vous souhaitez limiter les risques, vous pouvez désactiver
// la découverte des vendors ou limiter aux packages autorisés
class VendorDirectiveDiscovery
{
    private const ALLOWED_PACKAGES = [
        'andydefer/laravel-directive',
        'trusted-vendor/trusted-package',
    ];
    
    private function shouldScanPackage(string $package): bool
    {
        return in_array($package, self::ALLOWED_PACKAGES, true);
    }
}
```