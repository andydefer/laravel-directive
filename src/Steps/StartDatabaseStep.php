<?php

// src/Steps/StartDatabaseStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Contracts\Configs\DatabaseTestingConfigInterface;
use AndyDefer\Directive\Enums\StepResultStatus;
use AndyDefer\Directive\Enums\TestingStep;
use AndyDefer\Directive\Records\DatabaseConnectionRecord;
use Illuminate\Database\Capsule\Manager;
use PDO;
use PDOException;

final class StartDatabaseStep implements DirectiveTestingStepInterface
{
    private DatabaseTestingConfigInterface $config;

    public function __construct(?DatabaseTestingConfigInterface $config = null)
    {
        $this->config = $config ?? new DirectiveTestingConfig;
    }

    public function supports(DirectiveTestingContext $context): bool
    {
        return $context->shouldBootLaravel() && ! $context->hasDatabaseConnection();
    }

    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext
    {
        $tempDir = $context->getTempDir();

        if ($tempDir === null) {
            $context->addStepResult(
                step_name: TestingStep::START_DATABASE,
                status: StepResultStatus::FAILED,
                message: 'Cannot start database: temporary directory is null'
            );

            return $next($context);
        }

        try {
            $record = $this->createConnectionRecord($tempDir);
            $connection = $this->establishConnection($record);

            $context->setDatabaseConnection($connection);
            $context->setDatabaseConnectionRecord($record);

            // Enregistrer la connexion PDO dans l'application Laravel pour Eloquent
            $app = $context->getLaravelApp();
            if ($app !== null) {
                $this->setupEloquentConnection($app, $record);
            }

            $context->addStepResult(
                step_name: TestingStep::START_DATABASE,
                status: StepResultStatus::SUCCESS,
                message: sprintf(
                    'Database started successfully (driver: %s)',
                    $record->driver
                )
            );
        } catch (\Exception $e) {
            $context->addStepResult(
                step_name: TestingStep::START_DATABASE,
                status: StepResultStatus::FAILED,
                message: 'Failed to start database: '.$e->getMessage()
            );
        }

        return $next($context);
    }

    private function setupEloquentConnection(object $app, DatabaseConnectionRecord $record): void
    {
        $capsule = new Manager($app);

        $config = [
            'driver' => $record->driver,
            'database' => $record->sqlite_database ?? $record->mysql_database,
            'prefix' => '',
        ];

        if ($record->driver === 'mysql') {
            $config['host'] = $record->mysql_host;
            $config['port'] = $record->mysql_port;
            $config['username'] = $record->mysql_username;
            $config['password'] = $record->mysql_password;
            $config['charset'] = $record->mysql_charset;
            $config['collation'] = 'utf8mb4_unicode_ci';
        }

        $capsule->addConnection($config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        if (isset($app['events'])) {
            $capsule->setEventDispatcher($app['events']);
        }
    }

    private function createConnectionRecord(string $tempDir): DatabaseConnectionRecord
    {
        $driver = $this->config->getDriver();

        if ($driver === 'sqlite') {
            $database = $this->config->getSqliteDatabase();

            if ($database !== ':memory:') {
                $database = $tempDir.'/'.$database;
            }

            return new DatabaseConnectionRecord(
                driver: 'sqlite',
                sqlite_database: $database,
                mysql_host: null,
                mysql_port: null,
                mysql_database: null,
                mysql_username: null,
                mysql_password: null,
                mysql_charset: null,
                connection_timeout: $this->config->getConnectionTimeout(),
                max_retries: $this->config->getMaxRetries(),
                retry_delay_ms: $this->config->getRetryDelayMs(),
                is_connected: false,
                error_message: null
            );
        }

        if ($driver === 'mysql') {
            return new DatabaseConnectionRecord(
                driver: 'mysql',
                sqlite_database: null,
                mysql_host: $this->config->getMysqlHost(),
                mysql_port: $this->config->getMysqlPort(),
                mysql_database: $this->config->getMysqlDatabase(),
                mysql_username: $this->config->getMysqlUsername(),
                mysql_password: $this->config->getMysqlPassword(),
                mysql_charset: $this->config->getMysqlCharset(),
                connection_timeout: $this->config->getConnectionTimeout(),
                max_retries: $this->config->getMaxRetries(),
                retry_delay_ms: $this->config->getRetryDelayMs(),
                is_connected: false,
                error_message: null
            );
        }

        throw new \RuntimeException("Unsupported database driver: {$driver}");
    }

    private function establishConnection(DatabaseConnectionRecord $record): PDO
    {
        $dsn = $this->buildDsn($record);

        if ($dsn === null) {
            throw new \RuntimeException("Cannot build DSN for driver: {$record->driver}");
        }

        $maxRetries = $record->max_retries;
        $retryDelayMs = $record->retry_delay_ms;
        $timeout = $record->connection_timeout;

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $pdo = new PDO(
                    $dsn,
                    $record->mysql_username,
                    $record->mysql_password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => $timeout,
                    ]
                );

                if ($record->driver === 'sqlite' && $record->sqlite_database !== ':memory:') {
                    $pdo->exec('PRAGMA foreign_keys = ON');
                }

                if ($record->driver === 'mysql') {
                    $pdo->exec("SET NAMES '{$record->mysql_charset}'");
                }

                return $pdo;
            } catch (PDOException $e) {
                $lastException = $e;

                if ($attempt < $maxRetries) {
                    usleep($retryDelayMs * 1000);
                }
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Failed to connect to database after %d attempts: %s',
                $maxRetries,
                $lastException?->getMessage()
            )
        );
    }

    private function buildDsn(DatabaseConnectionRecord $record): ?string
    {
        if ($record->driver === 'sqlite') {
            return "sqlite:{$record->sqlite_database}";
        }

        if ($record->driver === 'mysql' && $record->mysql_host !== null) {
            return sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $record->mysql_host,
                $record->mysql_port,
                $record->mysql_database,
                $record->mysql_charset
            );
        }

        return null;
    }
}
