<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Records\PathSegmentsRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Service for parsing path strings into structured segments.
 *
 * Parses a path like "admin/user/UserRepository" into:
 * - Original segments: ["admin", "user"]
 * - PascalCase segments: ["Admin", "User"]
 * - Class name: "UserRepository"
 * - Subpath: "Admin/User"
 * - Full path: "Admin/User/UserRepository"
 *
 * @author Andy Defer
 */
class PathSegmentsParserService
{
    public function __construct(
        private readonly StringCaseConverterService $caseConverter
    ) {}

    /**
     * Extract path segments from a name string.
     *
     * @param string $name Path string with segments separated by slashes
     * @return PathSegmentsRecord Record containing all extracted path information
     *
     * @example
     * $parser->parse('admin/user/UserRepository');
     * // Returns PathSegmentsRecord with:
     * // - segments: ["admin", "user"]
     * // - pascalSegments: ["Admin", "User"]
     * // - className: "UserRepository"
     * // - subPath: "Admin/User"
     * // - fullPath: "Admin/User/UserRepository"
     */
    public function parse(string $name): PathSegmentsRecord
    {
        $segments = explode(DIRECTORY_SEPARATOR, $name);
        $className = array_pop($segments);

        $segmentsCollection = $this->createStringCollection($segments);
        $pascalSegments = $this->createPascalCaseSegments($segments);

        $subPath = $pascalSegments->isNotEmpty() ? $pascalSegments->join(DIRECTORY_SEPARATOR) : '';
        $fullPath = $subPath ? $subPath . DIRECTORY_SEPARATOR . $className : $className;

        return new PathSegmentsRecord(
            segments: $segmentsCollection,
            pascalSegments: $pascalSegments,
            className: $className,
            subPath: $subPath,
            fullPath: $fullPath,
        );
    }

    /**
     * Validate if a path string has valid format.
     *
     * @param string $name Path string to validate
     * @return bool True if format is valid
     */
    public function isValid(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        $doubleSeparator = DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR;

        return !str_contains($name, $doubleSeparator) &&
            !str_starts_with($name, DIRECTORY_SEPARATOR) &&
            !str_ends_with($name, DIRECTORY_SEPARATOR);
    }

    /**
     * Get the class name from a path string.
     *
     * @param string $name Path string
     * @return string Last segment as class name
     */
    public function extractClassName(string $name): string
    {
        $segments = explode(DIRECTORY_SEPARATOR, $name);
        return array_pop($segments);
    }

    /**
     * Get directory segments from a path string.
     *
     * @param string $name Path string
     * @return array<int, string> Directory segments (all except last)
     */
    public function extractDirectorySegments(string $name): array
    {
        $segments = explode(DIRECTORY_SEPARATOR, $name);
        array_pop($segments);
        return $segments;
    }

    /**
     * Create a StringTypedCollection from an array of strings.
     *
     * @param array<string> $items Items to add to the collection
     * @return StringTypedCollection Collection containing the items
     */
    private function createStringCollection(array $items): StringTypedCollection
    {
        $collection = new StringTypedCollection();
        foreach ($items as $item) {
            $collection->add($item);
        }
        return $collection;
    }

    /**
     * Create a collection of PascalCase converted path segments.
     *
     * @param array<string> $segments Original path segments
     * @return StringTypedCollection Collection of PascalCase segments
     */
    private function createPascalCaseSegments(array $segments): StringTypedCollection
    {
        $pascalSegments = new StringTypedCollection();
        foreach ($segments as $segment) {
            $pascalSegments->add($this->caseConverter->toPascalCase($segment));
        }
        return $pascalSegments;
    }
}
