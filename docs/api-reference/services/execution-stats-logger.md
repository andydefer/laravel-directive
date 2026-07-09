# ExecutionStatsLogger - Référence Technique

## Description

Service de journalisation des statistiques d'exécution au format JSONL. Enregistre chaque exécution de directive dans des fichiers horaires journaliers pour une analyse ultérieure.

## Hiérarchie / Implémentations

```
ExecutionStatsLogger
    ├── JsonlService (écriture JSONL)
    ├── TemporalPathStrategy (organisation horaire)
    └── Console (affichage des avertissements)
```

## Rôle principal

`ExecutionStatsLogger` est le système de télémétrie du package Directive. Il permet de :

- Journaliser chaque exécution de directive avec ses métriques (temps, mémoire)
- Organiser les logs par date et heure (stratégie temporelle)
- Générer des résumés statistiques
- Formater la mémoire en unités lisibles
- Capturer les erreurs de manière silencieuse

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `DirectiveConfigInterface` - Configuration du package
- `FileSystemInterface` - Opérations sur le système de fichiers
- `Console` - Affichage des avertissements
- `JsonlService` - Service d'écriture JSONL
- `Carbon` - Manipulation des dates
- PHP 8.1+

## API / Méthodes publiques

### `__construct(DirectiveConfigInterface $config, FileSystemInterface $fileSystem, Console $console)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `DirectiveConfigInterface` | Configuration du package |
| `$fileSystem` | `FileSystemInterface` | Service de système de fichiers |
| `$console` | `Console` | Sortie console pour les avertissements |

**Retourne :** `void`

**Exemple :**
```php
$logger = new ExecutionStatsLogger(
    $config,
    $fileSystem,
    new Console()
);
```

---

### `log(ExecutionStatsRecord $record, ?MapCollection $context = null): void`

Journalise les statistiques d'exécution.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `ExecutionStatsRecord` | Statistiques d'exécution |
| `$context` | `?MapCollection` | Contexte partagé (optionnel) |

**Retourne :** `void`

**Exceptions :** Aucune (les erreurs sont capturées et affichées en avertissement)

**Exemple :**
```php
$record = new ExecutionStatsRecord(
    command: 'greet John',
    directiveClass: GreetDirective::class,
    signature: 'greet {name}',
    exitCode: ExitCode::SUCCESS,
    duration: 0.012,
    memoryUsage: 1024,
    peakMemoryUsage: 2048,
    callsCount: 0
);

$context = MapCollection::from(['user' => 'John', 'role' => 'admin']);
$logger->log($record, $context);
```

---

### `setBasePath(string $path): self`

Définit un chemin de base personnalisé pour les logs.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Chemin absolu vers le dossier des logs |

**Retourne :** `self` - Instance fluide

**Exemple :**
```php
$logger->setBasePath('/var/log/directive');
// Les logs seront écrits dans /var/log/directive/2026-07-09/11.jsonl
```

---

### `getBasePath(): string`

Retourne le chemin de base actuel.

**Retourne :** `string` - Chemin absolu du dossier des logs

**Exemple :**
```php
$path = $logger->getBasePath();
echo "Logs stored in: $path\n";
// /var/www/project/.directive
```

---

### `getJsonlService(): JsonlService`

Retourne le service JSONL utilisé pour l'écriture.

**Retourne :** `JsonlService` - Instance du service

**Exemple :**
```php
$jsonlService = $logger->getJsonlService();
$jsonlService->flushBuffer(); // Forcer l'écriture
```

---

### `getSummary(): array`

Génère un résumé statistique des logs du jour.

**Retourne :** `array` - Tableau associatif avec les statistiques

**Structure du tableau :**
```php
[
    'total' => 42,                // Total d'exécutions
    'success' => 38,              // Exécutions réussies
    'failed' => 4,                // Exécutions échouées
    'success_rate' => 90.48,      // Taux de réussite (%)
    'avg_duration' => 0.015,      // Durée moyenne (secondes)
    'avg_memory' => 2048.5,       // Mémoire moyenne (octets)
    'total_calls' => 156,         // Appels internes totaux
    'avg_calls' => 3.71,          // Appels internes moyens
]
```

**Exemple :**
```php
$summary = $logger->getSummary();

echo "=== Execution Summary ===\n";
echo "Total: {$summary['total']}\n";
echo "Success: {$summary['success']}\n";
echo "Failed: {$summary['failed']}\n";
echo "Success rate: {$summary['success_rate']}%\n";
echo "Average duration: {$summary['avg_duration']}s\n";
echo "Average memory: " . $this->formatMemory($summary['avg_memory']) . "\n";
```

---

## Cas d'utilisation

### Cas 1 : Journalisation standard dans le kernel

```php
<?php

use AndyDefer\Directive\Records\ExecutionStatsRecord;
use AndyDefer\Directive\Services\ExecutionStatsLogger;

class DirectiveKernel
{
    private ExecutionStatsLogger $logger;
    
    private function logExecution(ExecutionStatsRecord $record): void
    {
        // Journaliser avec le contexte partagé
        $this->logger->log($record, $this->context);
    }
}

// Exemple d'enregistrement
$record = new ExecutionStatsRecord(...);
$kernel->logExecution($record);
// Écrit dans .directive/2026-07-09/11.jsonl
```

### Cas 2 : Génération d'un rapport d'activité

```php
<?php

$summary = $logger->getSummary();

echo "=== Rapport d'activité des directives ===\n";
echo "Date: " . date('Y-m-d') . "\n";
echo "Total d'exécutions: " . $summary['total'] . "\n";
echo "Taux de succès: " . $summary['success_rate'] . "%\n";
echo "Durée moyenne: " . round($summary['avg_duration'] * 1000, 2) . " ms\n";
echo "Mémoire moyenne: " . $summary['avg_memory'] . " octets\n";

if ($summary['failed'] > 0) {
    echo "\n⚠️ Attention: " . $summary['failed'] . " exécutions ont échoué\n";
}
```

### Cas 3 : Diagnostic des performances

```php
<?php

$summary = $logger->getSummary();

if ($summary['avg_duration'] > 1.0) {
    echo "⚠️ Performance dégradée: durée moyenne > 1s\n";
}

if ($summary['avg_memory'] > 10 * 1024 * 1024) {
    echo "⚠️ Consommation mémoire élevée: " . 
         round($summary['avg_memory'] / 1024 / 1024, 2) . " MB\n";
}

// Analyser les logs individuels
$logs = $logger->getJsonlService()->readAll(
    $logger->getBasePath() . '/' . date('Y-m-d') . '/' . date('H') . '.jsonl'
);

// Trouver les exécutions les plus lentes
usort($logs, function($a, $b) {
    return ($b['payload']['duration_seconds'] ?? 0) - ($a['payload']['duration_seconds'] ?? 0);
});

$slowest = array_slice($logs, 0, 5);
echo "\nTop 5 des exécutions les plus lentes:\n";
foreach ($slowest as $log) {
    $duration = $log['payload']['duration_seconds'] ?? 0;
    $command = $log['payload']['command'] ?? 'unknown';
    echo "- $command: " . round($duration * 1000, 2) . " ms\n";
}
```

### Cas 4 : Configuration personnalisée du chemin de logs

```php
<?php

use AndyDefer\Directive\Services\ExecutionStatsLogger;

// Logs dans le dossier standard
$logger->log($record);

// Changer de chemin
$logger->setBasePath('/custom/log/path');
$logger->log($record);
// Écrit dans /custom/log/path/2026-07-09/11.jsonl

// Retour au chemin par défaut
$logger->setBasePath($logger->getBasePath());
```

### Cas 5 : Analyse des erreurs

```php
<?php

$logs = $logger->getJsonlService()->readAll(
    $logger->getBasePath() . '/' . date('Y-m-d') . '/' . date('H') . '.jsonl'
);

$errors = array_filter($logs, function($log) {
    return ($log['payload']['success'] ?? true) === false;
});

if (count($errors) > 0) {
    echo "❌ " . count($errors) . " erreurs détectées\n";
    
    foreach ($errors as $error) {
        $command = $error['payload']['command'] ?? 'unknown';
        $exitCode = $error['payload']['exit_code_label'] ?? 'unknown';
        $errorMsg = $error['payload']['error'] ?? 'No details';
        
        echo "- $command: [$exitCode] $errorMsg\n";
    }
}
```

---

## Flux d'exécution

```
log($record, $context)
    ↓
buildLogRecord($record, $context)
    ├── extraire les métriques du record
    ├── ajouter le contexte si présent
    ├── déterminer le niveau (info/error)
    └── créer LogJsonlRecord
    ↓
jsonlService->write($logRecord)
    ├── appliquer TemporalPathStrategy
    │   └── base_path/YYYY-MM-DD/HH.jsonl
    ├── écrire la ligne JSON
    └── bufferiser (écriture différée)
    ↓
En cas d'erreur:
    └── console->alertWarning()
```

### Structure du log

```json
{
    "time": "2026-07-09T11:45:23+00:00",
    "level": "info",
    "type": "directive_execution",
    "payload": {
        "command": "greet John",
        "directive_class": "App\\Directives\\GreetDirective",
        "signature": "greet {name}",
        "exit_code": 0,
        "exit_code_label": "Success",
        "success": true,
        "duration_seconds": 0.012,
        "memory_bytes": 1024,
        "memory_human": "1.00 KB",
        "peak_memory_bytes": 2048,
        "peak_memory_human": "2.00 KB",
        "calls_count": 0,
        "context": {
            "user": "John",
            "role": "admin"
        }
    }
}
```

---

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Échec d'écriture du fichier | Avertissement console, exécution continue |
| Répertoire de logs inexistant | Créé automatiquement par TemporalPathStrategy |
| Permissions insuffisantes | Avertissement console |
| JSON invalide dans le payload | Avertissement console |
| Fichier de log corrompu | Ignoré silencieusement |

**Aucune exception n'est levée.** Les erreurs sont capturées et affichées en avertissement.

---

## Intégration

### Avec DirectiveKernel

```php
// Le kernel utilise le logger automatiquement
$kernel = DirectiveKernel::init($container);

// Configuration du chemin
$kernel->setLogBasePath('/var/log/directive');

// Chaque exécution est journalisée
$kernel->run($argv);
```

### Avec DirectiveTestingService

```php
$testingService = new DirectiveTestingService($container);
$kernel = $testingService->getKernel();
$kernel->setLogBasePath(sys_get_temp_dir() . '/logs');
// Les logs de test sont isolés
```

### Dans un script personnalisé

```php
<?php

use AndyDefer\Directive\Services\ExecutionStatsLogger;

$logger = new ExecutionStatsLogger($config, $fileSystem, new Console());

// Journaliser manuellement
$record = new ExecutionStatsRecord(...);
$logger->log($record);

// Analyser les logs
$summary = $logger->getSummary();
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `log()` | O(1) | Écriture différée dans le buffer |
| `getSummary()` | O(n) | n = nombre de logs du jour |
| `readAllLogs()` | O(n) | n = nombre de logs du jour |
| `setBasePath()` | O(1) | Reconfiguration du service |

**Optimisations :**
- Écriture différée (buffer) pour réduire les I/O
- Stratégie temporelle pour organiser les fichiers
- Lecture groupée pour les analyses
- Format JSONL compact

**Mémoire :**
- Les logs sont bufferisés (taille configurable)
- `getSummary()` charge tous les logs du jour en mémoire
- Les fichiers JSONL sont petits (quelques KB par heure)

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

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\Directive\Records\ExecutionStatsRecord;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Config\Repository as ConfigRepository;

// 1. Configuration
$configRepo = new ConfigRepository([
    'directive' => [
        'base_path' => __DIR__,
        'log_base_path' => __DIR__ . '/.logs',
    ]
]);

$config = new DirectiveConfig($configRepo);
$fileSystem = new FileSystemService();
$console = new Console();

// 2. Création du logger
$logger = new ExecutionStatsLogger($config, $fileSystem, $console);

// 3. Journalisation d'exécutions
echo "=== Journalisation des exécutions ===\n";

for ($i = 1; $i <= 5; $i++) {
    $record = new ExecutionStatsRecord(
        command: "test-command $i",
        directiveClass: "App\\Directives\\TestDirective",
        signature: "test-command {count}",
        exitCode: $i % 2 === 0 ? ExitCode::SUCCESS : ExitCode::RUNTIME_ERROR,
        duration: rand(10, 100) / 1000.0,
        memoryUsage: rand(1024, 8192),
        peakMemoryUsage: rand(8192, 16384),
        callsCount: rand(0, 3)
    );
    
    $context = MapCollection::from([
        'iteration' => $i,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $logger->log($record, $context);
    echo "✅ Logged execution $i\n";
}

// 4. Forcer l'écriture du buffer
$logger->getJsonlService()->flushBuffer();
echo "\nLogs flushed to disk\n";

// 5. Récupération du résumé
echo "\n=== Résumé des logs ===\n";
$summary = $logger->getSummary();

echo "Total: " . $summary['total'] . "\n";
echo "Succès: " . $summary['success'] . "\n";
echo "Échecs: " . $summary['failed'] . "\n";
echo "Taux de succès: " . $summary['success_rate'] . "%\n";
echo "Durée moyenne: " . round($summary['avg_duration'] * 1000, 2) . " ms\n";
echo "Mémoire moyenne: " . round($summary['avg_memory'] / 1024, 2) . " KB\n";

// 6. Analyse détaillée
echo "\n=== Analyse détaillée ===\n";

$logs = $logger->getJsonlService()->readAll(
    $logger->getBasePath() . '/' . date('Y-m-d') . '/' . date('H') . '.jsonl'
);

echo "Logs lus: " . count($logs) . "\n";

if (count($logs) > 0) {
    // Trouver l'exécution la plus lente
    $slowest = array_reduce($logs, function($carry, $log) {
        $duration = $log['payload']['duration_seconds'] ?? 0;
        if ($carry === null || $duration > $carry['duration']) {
            return ['duration' => $duration, 'log' => $log];
        }
        return $carry;
    }, null);
    
    if ($slowest) {
        echo "Exécution la plus lente:\n";
        echo "  Commande: " . ($slowest['log']['payload']['command'] ?? 'unknown') . "\n";
        echo "  Durée: " . round($slowest['duration'] * 1000, 2) . " ms\n";
    }
    
    // Compter par niveau
    $levels = [];
    foreach ($logs as $log) {
        $level = $log['level'] ?? 'unknown';
        $levels[$level] = ($levels[$level] ?? 0) + 1;
    }
    
    echo "\nRépartition par niveau:\n";
    foreach ($levels as $level => $count) {
        echo "  $level: $count\n";
    }
}

// 7. Modification du chemin de logs
echo "\n=== Changement du chemin ===\n";
$newPath = __DIR__ . '/.new_logs';
$logger->setBasePath($newPath);

echo "Nouveau chemin: " . $logger->getBasePath() . "\n";

// Journaliser dans le nouveau chemin
$record = new ExecutionStatsRecord(
    command: 'test-new-path',
    directiveClass: 'App\\Directives\\TestDirective',
    signature: 'test-new-path',
    exitCode: ExitCode::SUCCESS,
    duration: 0.001,
    memoryUsage: 512,
    peakMemoryUsage: 1024,
    callsCount: 0
);

$logger->log($record);
$logger->getJsonlService()->flushBuffer();

echo "✅ Log écrit dans le nouveau chemin\n";

// 8. Nettoyage
echo "\n=== Fin du test ===\n";
echo "Fichiers de logs créés:\n";
$basePath = $logger->getBasePath();
if (is_dir($basePath)) {
    $files = glob($basePath . '/*/*.jsonl');
    foreach ($files as $file) {
        echo "  - " . basename($file) . "\n";
        echo "    Taille: " . round(filesize($file) / 1024, 2) . " KB\n";
    }
}
```

## Voir aussi

- `ExecutionStatsRecord` - Enregistrement des statistiques
- `JsonlService` - Service d'écriture JSONL
- `TemporalPathStrategy` - Stratégie de chemin temporel
- `DirectiveConfigInterface` - Configuration du package
- `Console` - Service de sortie console