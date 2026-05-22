<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Collections\TypedRecords;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

class DirectiveKernel
{
    public function __construct(
        private readonly DirectiveExecutionService $service,
    ) {}

    public function run(array $argv): ExitCode
    {
        $signature = $argv[1] ?? '';
        $args = array_slice($argv, 2);
        $arguments = new StringTypedCollection();

        foreach ($args as $arg) {
            $arguments->add($arg);
        }

        return $this->service->execute(new DirectiveExecutionRecord(
            signature: $signature,
            arguments: $arguments,
        ));
    }
}
