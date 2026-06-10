<?php

// src/Steps/CreateTempDirectoryStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Enums\StepResultStatus;
use AndyDefer\Directive\Enums\TestingStep;

final class CreateTempDirectoryStep implements DirectiveTestingStepInterface
{
    public function supports(DirectiveTestingContext $context): bool
    {
        return ! $context->hasTempDir();
    }

    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext
    {
        $prefix = $context->getConfig()->tempDirectoryPrefix();
        $permission = $context->getConfig()->tempDirectoryPermission();

        $tempDir = sys_get_temp_dir().'/'.$prefix.uniqid();

        if (mkdir($tempDir, $permission->value(), true)) {
            $context->setTempDir($tempDir);
            $context->addStepResult(
                step_name: TestingStep::CREATE_TEMP_DIRECTORY,
                status: StepResultStatus::SUCCESS,
                message: $tempDir
            );
        } else {
            $context->addStepResult(
                step_name: TestingStep::CREATE_TEMP_DIRECTORY,
                status: StepResultStatus::FAILED,
                message: "Failed to create temporary directory: {$tempDir}"
            );
        }

        return $next($context);
    }
}
