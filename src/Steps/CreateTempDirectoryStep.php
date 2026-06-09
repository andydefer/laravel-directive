<?php
// src/Steps/CreateTempDirectoryStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Contexts\DirectiveTestingContext;

/**
 * Step that creates the temporary directory for testing.
 *
 * This step is responsible for:
 * - Generating a unique temporary directory path
 * - Creating the directory with proper permissions
 * - Storing the path in the context
 *
 * @author Andy Defer
 */
final class CreateTempDirectoryStep implements DirectiveTestingStepInterface
{
    /**
     * {@inheritDoc}
     */
    public function supports(DirectiveTestingContext $context): bool
    {
        return !$context->hasTempDir();
    }

    /**
     * {@inheritDoc}
     */
    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext
    {
        $prefix = $context->getConfig()->tempDirectoryPrefix();
        $permission = $context->getConfig()->tempDirectoryPermission();

        $tempDir = sys_get_temp_dir() . '/' . $prefix . uniqid();
        mkdir($tempDir, $permission->value(), true);

        $context->setTempDir($tempDir);
        $context->addStepResult('create_temp_directory', $tempDir);

        return $next($context);
    }
}
