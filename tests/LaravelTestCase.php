<?php

// tests/LaravelTestCase.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for tests that need Laravel and database.
 * Initializes Facades, Eloquent, and database connections.
 */
abstract class LaravelTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Initialisation de Laravel et de la base de données
        $this->initializeLaravel();
    }

    protected function tearDown(): void
    {
        // Nettoyage
        parent::tearDown();
    }

    private function initializeLaravel(): void
    {
        // Démarrer Laravel si nécessaire
        // Configurer les Facades
        // Initialiser la base de données
    }
}
