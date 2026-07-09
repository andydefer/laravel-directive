<?php

declare(strict_types=1);

namespace AndyDefer\Directive\BuiltIn;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use Carbon\Carbon;

/**
 * Built-in directive that cleans old execution log files.
 *
 * Removes log files that exceed the configured retention period.
 * Supports dry-run mode for previewing deletions and verbose output
 * for detailed information about which files would be affected.
 */
final class CleanLogsDirective extends AbstractDirective
{
    private const INDENTATION_LEVEL = 2;

    public function getSignature(): string
    {
        return 'clean-directive-logs {days=30} {--dry-run} {--verbose}';
    }

    public function getDescription(): string
    {
        return 'Remove old execution log files that exceed the retention period';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['log-directive-clean', 'ldc']);
    }

    protected function execute(): ExitCode
    {
        echo "\n🔵 [CleanLogsDirective::execute] DÉBUT DE L'EXÉCUTION\n";

        $container = $this->getContainer();
        $logger = $container->make(ExecutionStatsLogger::class);
        $fileSystem = $container->make(FileSystemInterface::class);

        $daysRaw = $this->argument('days');
        echo '🔵 [execute] daysRaw: '.var_export($daysRaw, true)."\n";

        // ✅ Vérifier si la valeur est numérique
        if ($daysRaw !== null && ! is_numeric($daysRaw)) {
            $this->error('Days must be a valid number');

            return ExitCode::INVALID_ARGUMENT;
        }

        $days = (int) ($daysRaw ?? 30);
        $dryRun = $this->flag('dry-run');
        $verbose = $this->flag('verbose');

        echo "🔵 [execute] days: {$days}, dryRun: ".($dryRun ? 'true' : 'false').', verbose: '.($verbose ? 'true' : 'false')."\n";

        if ($days < 1) {
            $this->error('Days must be at least 1');

            return ExitCode::INVALID_ARGUMENT;
        }

        $basePath = $logger->getBasePath();
        echo "🔵 [execute] basePath récupéré du logger: '{$basePath}'\n";

        $cutoffDateTime = Carbon::now()->subDays($days)->format('Y-m-d');
        echo "🔵 [execute] cutoffDateTime (date limite): '{$cutoffDateTime}'\n";

        $filesToDelete = $this->getFilesToDelete($fileSystem, $basePath, $cutoffDateTime);

        $count = count($filesToDelete);
        echo "🔵 [execute] Nombre de fichiers à supprimer: {$count}\n";

        if ($verbose) {
            $this->displayStatistics($filesToDelete, $count, $days);
        }

        if ($count === 0) {
            $this->info('No log files to delete.');
            echo "🔵 [execute] FIN - Aucun fichier trouvé\n";

            return ExitCode::SUCCESS;
        }

        if ($dryRun) {
            echo "🔵 [execute] Mode DRY RUN\n";

            return $this->handleDryRun($filesToDelete, $count);
        }

        echo "🔵 [execute] Mode SUPPRESSION RÉELLE\n";

        return $this->handleDeletion($fileSystem, $filesToDelete, $count);
    }

    /**
     * Get files to delete based on cutoff date.
     *
     * @return array<int, string>
     */
    private function getFilesToDelete(FileSystemInterface $fileSystem, string $basePath, string $cutoffDateTime): array
    {
        echo "\n🔍 [getFilesToDelete] DÉBUT DE LA RECHERCHE\n";
        echo "🔍 [getFilesToDelete] basePath: '{$basePath}'\n";
        echo "🔍 [getFilesToDelete] cutoffDateTime: '{$cutoffDateTime}'\n";

        $filesToDelete = [];

        // ✅ Vérifier si le dossier existe
        $isDirectory = $fileSystem->isDirectory($basePath);
        echo "🔍 [getFilesToDelete] isDirectory('{$basePath}'): ".($isDirectory ? 'true' : 'false')."\n";

        if (! $isDirectory) {
            echo "⚠️ [getFilesToDelete] Le dossier n'existe pas ou n'est pas accessible\n";

            return $filesToDelete;
        }

        // ✅ Lister le contenu du dossier
        echo "🔍 [getFilesToDelete] Contenu du dossier '{$basePath}':\n";
        try {
            $scandir = scandir($basePath);
            if ($scandir !== false) {
                foreach (array_diff($scandir, ['.', '..']) as $item) {
                    $fullPath = $basePath.DIRECTORY_SEPARATOR.$item;
                    echo "  - {$item} ".(is_dir($fullPath) ? '(dossier)' : '(fichier)')."\n";
                }
            } else {
                echo "  ❌ Impossible de lire le dossier\n";
            }
        } catch (\Throwable $e) {
            echo '  ❌ Erreur lors de la lecture: '.$e->getMessage()."\n";
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        echo "🔍 [getFilesToDelete] Itération sur les fichiers...\n";

        $totalFiles = 0;
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $totalFiles++;
            echo "🔍 [getFilesToDelete] Fichier #{$totalFiles}: '{$file->getPathname()}'\n";

            $isFile = $file->isFile();
            $extension = $file->getExtension();
            $mTime = $file->getMTime();
            $mTimeFormatted = date('Y-m-d H:i:s', $mTime);

            echo '🔍 [getFilesToDelete]   - isFile: '.($isFile ? 'true' : 'false')."\n";
            echo "🔍 [getFilesToDelete]   - extension: '{$extension}'\n";
            echo "🔍 [getFilesToDelete]   - mTime: {$mTimeFormatted} (timestamp: {$mTime})\n";

            if (! $isFile || $extension !== 'jsonl') {
                echo "⚠️ [getFilesToDelete]   - IGNORÉ (pas un fichier jsonl valide)\n";

                continue;
            }

            $fileModifiedTime = Carbon::createFromTimestamp($mTime)->format('Y-m-d');
            echo "🔍 [getFilesToDelete]   - fileModifiedTime: '{$fileModifiedTime}'\n";
            echo "🔍 [getFilesToDelete]   - comparaison: '{$fileModifiedTime}' < '{$cutoffDateTime}' ?\n";

            if ($fileModifiedTime < $cutoffDateTime) {
                echo "✅ [getFilesToDelete]   - AJOUTÉ à la liste des fichiers à supprimer\n";
                $filesToDelete[] = $file->getPathname();
            } else {
                echo "⏭️ [getFilesToDelete]   - CONSERVÉ (plus récent que la date limite)\n";
            }
        }

        echo "🔍 [getFilesToDelete] Total fichiers analysés: {$totalFiles}\n";
        echo '🔍 [getFilesToDelete] Total fichiers à supprimer: '.count($filesToDelete)."\n";

        if (count($filesToDelete) > 0) {
            echo "🔍 [getFilesToDelete] Liste des fichiers à supprimer:\n";
            foreach ($filesToDelete as $file) {
                echo "  - {$file}\n";
            }
        }

        echo "🔍 [getFilesToDelete] FIN DE LA RECHERCHE\n\n";

        return $filesToDelete;
    }

    /**
     * Display statistics about the files to delete using KeyValue.
     *
     * @param  array<int, string>  $filesToDelete
     */
    private function displayStatistics(array $filesToDelete, int $count, int $days): void
    {
        echo "📊 [displayStatistics] Affichage des statistiques\n";

        $totalSize = 0;
        $dates = [];

        foreach ($filesToDelete as $file) {
            $size = filesize($file);
            $totalSize += $size;
            $dirName = dirname($file);
            $date = basename($dirName);
            $dates[] = $date;
            echo "📊 [displayStatistics] Fichier: {$file}, taille: {$size} bytes, date: {$date}\n";
        }

        $totalSizeMb = round($totalSize / 1024 / 1024, 2);
        $oldestDate = ! empty($dates) ? min($dates) : 'N/A';
        $newestDate = ! empty($dates) ? max($dates) : 'N/A';

        echo "📊 [displayStatistics] totalSizeMb: {$totalSizeMb}, oldestDate: {$oldestDate}, newestDate: {$newestDate}\n";

        $data = MapCollection::from([
            'Retention' => $days.' days',
            'Files to delete' => $count,
            'Total size' => $totalSizeMb.' MB',
            'Date range' => $oldestDate.' to '.$newestDate,
            'Base path' => dirname($filesToDelete[0] ?? ''),
        ]);

        $this->newLine();
        $this->info('📊 Log Statistics:');
        $this->getConsole()->raw(KeyValue::render($data, self::INDENTATION_LEVEL));
        $this->newLine();
    }

    /**
     * Handle dry-run mode.
     *
     * @param  array<int, string>  $filesToDelete
     */
    private function handleDryRun(array $filesToDelete, int $count): ExitCode
    {
        echo "🔵 [handleDryRun] DÉBUT - {$count} fichiers à supprimer\n";

        $this->getConsole()->alertWarning('🔍 DRY RUN MODE - No files will be deleted');
        $this->newLine();

        if ($this->flag('verbose')) {
            $this->line('Files that would be deleted:');
            foreach ($filesToDelete as $file) {
                $sizeKb = round(filesize($file) / 1024, 2);
                $fileName = basename($file);
                $date = basename(dirname($file));
                $this->line("  - {$date}/{$fileName} ({$sizeKb} KB)");
                echo "🔵 [handleDryRun] Fichier: {$date}/{$fileName} ({$sizeKb} KB)\n";
            }
            $this->newLine();
        }

        $this->info("ℹ️  Would delete {$count} file(s)");
        echo "🔵 [handleDryRun] FIN\n";

        return ExitCode::SUCCESS;
    }

    /**
     * Handle actual deletion.
     *
     * @param  array<int, string>  $filesToDelete
     */
    private function handleDeletion(FileSystemInterface $fileSystem, array $filesToDelete, int $count): ExitCode
    {
        echo "🔵 [handleDeletion] DÉBUT - Suppression de {$count} fichiers\n";

        $deleted = 0;
        $errors = 0;

        $this->info('Deleting log files...');

        foreach ($filesToDelete as $file) {
            echo "🔵 [handleDeletion] Tentative de suppression: {$file}\n";
            try {
                if ($fileSystem->delete($file)) {
                    $deleted++;
                    echo "✅ [handleDeletion] Supprimé: {$file}\n";
                    if ($this->flag('verbose')) {
                        $this->line('  ✓ Deleted: '.basename($file));
                    }
                } else {
                    $errors++;
                    echo "❌ [handleDeletion] Échec de suppression: {$file}\n";
                }
            } catch (\Throwable $e) {
                $errors++;
                echo "❌ [handleDeletion] Exception: {$file} - ".$e->getMessage()."\n";
                if ($this->flag('verbose')) {
                    $this->error('  ✗ Failed to delete: '.basename($file).' - '.$e->getMessage());
                }
            }
        }

        // Clean empty directories
        $basePath = dirname($filesToDelete[0] ?? '');
        echo "🔵 [handleDeletion] Nettoyage des dossiers vides à partir de: '{$basePath}'\n";

        while ($basePath && $basePath !== dirname($basePath)) {
            echo "🔵 [handleDeletion] Vérification du dossier: '{$basePath}'\n";

            $isDir = $fileSystem->isDirectory($basePath);
            $scandir = $isDir ? scandir($basePath) : false;
            $countScandir = $scandir !== false ? count($scandir) : 0;

            echo '🔵 [handleDeletion]   - isDirectory: '.($isDir ? 'true' : 'false')."\n";
            echo "🔵 [handleDeletion]   - nombre d'éléments: {$countScandir}\n";

            if ($isDir && $countScandir === 2) {
                echo "✅ [handleDeletion] Suppression du dossier vide: '{$basePath}'\n";
                $fileSystem->delete($basePath);
            } else {
                echo "⏭️ [handleDeletion] Dossier non vide ou inexistant, arrêt du nettoyage\n";
                break;
            }
            $basePath = dirname($basePath);
        }

        $this->newLine();
        if ($errors === 0) {
            $this->info("✅ Successfully deleted {$deleted} file(s)");
            echo "🔵 [handleDeletion] SUCCÈS - {$deleted} fichiers supprimés\n";
        } else {
            $this->getConsole()->alertWarning("⚠️  Deleted {$deleted} file(s), {$errors} error(s)");
            echo "🔵 [handleDeletion] PARTIEL - {$deleted} supprimés, {$errors} erreurs\n";
        }

        echo "🔵 [handleDeletion] FIN\n";

        return $errors === 0 ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }
}
