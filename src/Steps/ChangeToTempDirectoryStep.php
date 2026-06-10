<?php
// src/Steps/ChangeToTempDirectoryStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Enums\StepResultStatus;
use AndyDefer\Directive\Enums\TestingStep;

final class ChangeToTempDirectoryStep implements DirectiveTestingStepInterface
{
    public function supports(DirectiveTestingContext $context): bool
    {
        return $context->hasTempDir() && !$context->isInTempDirectory();
    }

    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext
    {
        $tempDir = $context->getTempDir();

        if ($tempDir === null) {
            $context->addStepResult(
                step_name: TestingStep::CHANGE_TO_TEMP_DIRECTORY,
                status: StepResultStatus::FAILED,
                message: "Cannot change to null temporary directory"
            );
            return $next($context);
        }

        $context->setOriginalCwd(getcwd());

        if (chdir($tempDir)) {
            $context->setInTempDirectory(true);
            $context->addStepResult(
                step_name: TestingStep::CHANGE_TO_TEMP_DIRECTORY,
                status: StepResultStatus::SUCCESS,
                message: $tempDir
            );
        } else {
            $context->addStepResult(
                step_name: TestingStep::CHANGE_TO_TEMP_DIRECTORY,
                status: StepResultStatus::FAILED,
                message: "Failed to change directory to: {$tempDir}"
            );
        }

        return $next($context);
    }
}
