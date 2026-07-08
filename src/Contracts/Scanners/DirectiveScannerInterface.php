<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Scanners;

interface DirectiveScannerInterface
{
    /**
     * Scan a directory recursively to find directive classes.
     *
     * @param  string  $directory  The directory to scan
     * @param  int  $maxDepth  Maximum recursion depth (default: 3)
     * @return array<string> List of fully qualified class names
     */
    public function scan(string $directory, int $maxDepth = 3): array;
}
