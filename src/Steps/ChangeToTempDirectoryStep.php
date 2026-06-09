<?php
// src/Steps/ChangeToTempDirectoryStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Contexts\DirectiveTestingContext;

/**
 * Step that changes the current working directory to the temporary directory.
 *
 * @author Andy Defer
 */
final class ChangeToTempDirectoryStep implements DirectiveTestingStepInterface
{
    public function supports(DirectiveTestingContext $context): bool
    {
        return $context->hasTempDir() && !$context->isInTempDirectory();
    }

    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext
    {
        $context->setOriginalCwd(getcwd());
        chdir($context->getTempDir());
        $context->addStepResult('change_to_temp_directory', $context->getTempDir());

        return $next($context);
    }
}
