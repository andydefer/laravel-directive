<?php
// src/Steps/BootstrapLaravelStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Enums\StepResultStatus;
use AndyDefer\Directive\Enums\TestingStep;
use Illuminate\Foundation\Application;

final class BootstrapLaravelStep implements DirectiveTestingStepInterface
{
    public function supports(DirectiveTestingContext $context): bool
    {
        return $context->shouldBootLaravel() && !$context->hasLaravelApp();
    }

    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext
    {
        $tempDir = $context->getTempDir();

        if ($tempDir === null) {
            $context->addStepResult(
                step_name: TestingStep::BOOTSTRAP_LARAVEL,
                status: StepResultStatus::FAILED,
                message: "Cannot bootstrap Laravel: temporary directory is null"
            );
            return $next($context);
        }

        $bootstrapPath = $tempDir . '/bootstrap/app.php';

        if (!file_exists($bootstrapPath)) {
            $context->addStepResult(
                step_name: TestingStep::BOOTSTRAP_LARAVEL,
                status: StepResultStatus::FAILED,
                message: "Bootstrap file not found: {$bootstrapPath}"
            );
            return $next($context);
        }

        try {
            $app = require $bootstrapPath;

            if (!$app instanceof Application) {
                throw new \RuntimeException('Bootstrap file did not return an Application instance');
            }

            $app->useStoragePath($tempDir . '/storage');
            $app->instance('path.config', $tempDir . '/config');

            $context->setLaravelApp($app);
            $context->addStepResult(
                step_name: TestingStep::BOOTSTRAP_LARAVEL,
                status: StepResultStatus::SUCCESS,
                message: "Laravel bootstrapped successfully"
            );
        } catch (\Exception $e) {
            $context->addStepResult(
                step_name: TestingStep::BOOTSTRAP_LARAVEL,
                status: StepResultStatus::FAILED,
                message: $e->getMessage()
            );
        }

        return $next($context);
    }
}
