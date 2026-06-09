<?php
// src/Steps/BootstrapLaravelStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use Illuminate\Foundation\Application;

/**
 * Step that bootstraps the Laravel application.
 *
 * @author Andy Defer
 */
final class BootstrapLaravelStep implements DirectiveTestingStepInterface
{
    public function supports(DirectiveTestingContext $context): bool
    {
        return $context->shouldBootLaravel() && !$context->hasLaravelApp();
    }

    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext
    {
        $tempDir = $context->getTempDir();
        $app = require $tempDir . '/bootstrap/app.php';

        $app->useStoragePath($tempDir . '/storage');
        $app->instance('path.config', $tempDir . '/config');

        $context->setLaravelApp($app);
        $context->addStepResult('bootstrap_laravel', get_class($app));

        return $next($context);
    }
}
