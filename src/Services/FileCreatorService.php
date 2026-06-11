<?php

// src/Services/FileCreatorService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contexts\FileCreationContext;
use AndyDefer\Directive\Contracts\Configs\FileCreatorConfigInterface;
use AndyDefer\Directive\Contracts\Services\FileSystemInterface;
use AndyDefer\Directive\Enums\FileCreationStep;
use AndyDefer\Directive\Records\FileCreationResultRecord;
use AndyDefer\Directive\Records\PathSegmentsRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Service for creating files from stub templates with variable replacement.
 *
 * This service handles the complete file creation workflow:
 * - Directory creation
 * - Stub file reading
 * - Variable replacement
 * - File writing
 *
 * The service is stateless; all mutable state is managed by the provided
 * FileCreationContext, making it testable and reusable.
 *
 * @author Andy Defer
 */
class FileCreatorService
{
    private FileSystemInterface $filesystem;

    /**
     * Create a new file creator service.
     *
     * @param  FileCreatorConfigInterface  $config  Configuration for directory permissions and paths
     * @param  FileSystemInterface|null  $filesystem  Optional filesystem instance (creates native default if not provided)
     */
    public function __construct(
        private readonly FileCreatorConfigInterface $config,
        ?FileSystemInterface $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new FileSystemService;
    }

    /**
     * Create a file from a stub template with variable replacement.
     *
     * This method handles the complete file creation workflow and updates the
     * context with the current step, any errors, and created directories/files.
     *
     * @param  string  $stubPath  Path to the stub template file
     * @param  string  $destinationPath  Absolute path where the file should be created
     * @param  ReplacementCollection  $replacements  Collection of placeholder-value pairs
     * @param  FileCreationContext  $context  Context tracking the creation state
     * @return FileCreationResultRecord Result containing success status and details
     */
    public function createFile(
        string $stubPath,
        string $destinationPath,
        ReplacementCollection $replacements,
        FileCreationContext $context,
    ): FileCreationResultRecord {
        $context->setCurrentStep(FileCreationStep::START);
        $context->setStubPath($stubPath);
        $context->setDestinationPath($destinationPath);

        // Check if file already exists and force mode is disabled
        if ($this->filesystem->exists($destinationPath) && ! $context->shouldForce()) {
            $context->setCurrentStep(FileCreationStep::FAILED);
            $context->setErrorMessage("File already exists: {$destinationPath}");

            return $this->createFailureResult($destinationPath, "File already exists: {$destinationPath}");
        }

        $context->setCurrentStep(FileCreationStep::CREATING_DIRECTORY);
        $this->ensureDirectoryExists(dirname($destinationPath), $context);

        if ($context->hasError()) {
            return $this->createFailureResult($destinationPath, $context->getErrorMessage());
        }

        $context->setCurrentStep(FileCreationStep::READING_STUB);
        $stubContent = $this->getStubContent($stubPath, $context);

        if ($context->hasError()) {
            return $this->createFailureResult($destinationPath, $context->getErrorMessage());
        }

        $context->setCurrentStep(FileCreationStep::REPLACING_VARIABLES);
        $this->logVariableReplacement($replacements, $context);

        $content = $this->replaceVariables($stubContent, $replacements);

        $context->setCurrentStep(FileCreationStep::WRITING_FILE);
        $writeSuccess = $this->writeFile($destinationPath, $content, $context);

        if (! $writeSuccess) {
            return $this->createFailureResult($destinationPath, "Cannot create file: {$destinationPath}");
        }

        $context->setCurrentStep(FileCreationStep::COMPLETED);
        $context->addCreatedFile($destinationPath);

        return new FileCreationResultRecord(
            success: true,
            destinationPath: $destinationPath,
            message: "File created successfully: {$destinationPath}",
        );
    }

    /**
     * Create a file from a stub using a name to automatically build the destination path.
     *
     * This method extracts path segments from the name, converts them to the
     * appropriate case, and builds the complete destination path.
     *
     * @param  string  $stubPath  Path to the stub template file
     * @param  string  $name  Name used to build the destination path (supports subdirectories like "Admin/UserTask")
     * @param  string  $baseDirectory  Base directory where the file should be created
     * @param  ReplacementCollection  $replacements  Collection of placeholder-value pairs
     * @param  FileCreationContext  $context  Context tracking the creation state
     * @return FileCreationResultRecord Result containing success status and details
     */
    public function createFileFromName(
        string $stubPath,
        string $name,
        string $baseDirectory,
        ReplacementCollection $replacements,
        FileCreationContext $context,
    ): FileCreationResultRecord {
        $segments = $this->extractPathSegments($name, $context);
        $destinationPath = $this->buildDestinationPath($baseDirectory, $segments);

        return $this->createFile($stubPath, $destinationPath, $replacements, $context);
    }

    /**
     * Convert a string from kebab-case or snake_case to PascalCase.
     *
     * Examples:
     * - "user-profile" → "UserProfile"
     * - "user_profile" → "UserProfile"
     * - "send-welcome-email-task" → "SendWelcomeEmailTask"
     *
     * @param  string  $string  Input string in kebab-case or snake_case
     * @param  FileCreationContext  $context  Context to log the transformation
     * @return string Converted string in PascalCase
     */
    public function toPascalCase(string $string, FileCreationContext $context): string
    {
        $result = str_replace(['-', '_'], ' ', $string);
        $result = ucwords($result);
        $result = str_replace(' ', '', $result);

        $context->addTransformationLog('toPascalCase', $string, $result);

        return $result;
    }

    /**
     * Convert a string from PascalCase to kebab-case.
     *
     * Examples:
     * - "UserProfile" → "user-profile"
     * - "SendWelcomeEmailTask" → "send-welcome-email-task"
     *
     * @param  string  $string  Input string in PascalCase
     * @param  FileCreationContext  $context  Context to log the transformation
     * @return string Converted string in kebab-case
     */
    public function toKebabCase(string $string, FileCreationContext $context): string
    {
        $result = strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $string));
        $context->addTransformationLog('toKebabCase', $string, $result);

        return $result;
    }

    /**
     * Extract path segments from a name string.
     *
     * Parses a path like "admin/user/UserRepository" into:
     * - Original segments: ["admin", "user"]
     * - PascalCase segments: ["Admin", "User"]
     * - Class name: "UserRepository"
     * - Subpath: "Admin/User"
     * - Full path: "Admin/User/UserRepository"
     *
     * @param  string  $name  Path string with segments separated by slashes
     * @param  FileCreationContext  $context  Context to store the extracted segments
     * @return PathSegmentsRecord Record containing all extracted path information
     */
    public function extractPathSegments(string $name, FileCreationContext $context): PathSegmentsRecord
    {
        $segments = explode('/', $name);
        $className = array_pop($segments);

        $segmentsCollection = $this->createStringCollection($segments);
        $pascalSegments = $this->createPascalCaseSegments($segments, $context);

        $subPath = $pascalSegments->isNotEmpty() ? $pascalSegments->join('/') : '';
        $fullPath = $subPath ? $subPath . '/' . $className : $className;

        $record = new PathSegmentsRecord(
            segments: $segmentsCollection,
            pascalSegments: $pascalSegments,
            className: $className,
            subPath: $subPath,
            fullPath: $fullPath,
        );

        $context->setCurrentSegments($record);

        return $record;
    }

    /**
     * Build a PHP namespace from a base namespace and path segments.
     *
     * @param  string  $baseNamespace  Base namespace (e.g., "App\\Tasks")
     * @param  PathSegmentsRecord  $segments  Path segments record
     * @param  FileCreationContext  $context  Context to store the built namespace
     * @return string Complete namespace with subpaths
     */
    public function buildNamespace(
        string $baseNamespace,
        PathSegmentsRecord $segments,
        FileCreationContext $context,
    ): string {
        if ($segments->subPath === '') {
            return $baseNamespace;
        }

        $namespace = $baseNamespace . '\\' . str_replace('/', '\\', $segments->subPath);
        $context->setBuiltNamespace($namespace);

        return $namespace;
    }

    /**
     * Build an absolute file path from a base directory and path segments.
     *
     * @param  string  $baseDirectory  Base directory relative to working directory
     * @param  PathSegmentsRecord  $segments  Path segments record
     * @param  FileCreationContext  $context  Context to store the built path
     * @return string Absolute file path
     */
    public function getAppPath(
        string $baseDirectory,
        PathSegmentsRecord $segments,
        FileCreationContext $context,
    ): string {
        $path = $this->buildPath($baseDirectory, $segments);
        $context->setBuiltPath($path);

        return $path;
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param  string  $path  Directory path to check/create
     * @param  FileCreationContext  $context  Context to track directory creation
     */
    private function ensureDirectoryExists(string $path, FileCreationContext $context): void
    {
        $context->setCurrentStep(FileCreationStep::CREATING_DIRECTORY);
        $context->setCurrentDirectory($path);

        if (! $this->filesystem->isDirectory($path)) {
            $permission = $this->config->directoryPermission();
            $this->filesystem->makeDirectory($path, $permission, true);
            $context->addCreatedDirectory($path);
        }
    }

    /**
     * Read and return the content of a stub file.
     *
     * @param  string  $stubPath  Path to the stub file
     * @param  FileCreationContext  $context  Context to store the content or error
     * @return string Stub file content, empty string on error
     */
    private function getStubContent(string $stubPath, FileCreationContext $context): string
    {
        try {
            $content = $this->filesystem->get($stubPath);
            $context->setStubContent($content);

            return $content;
        } catch (\RuntimeException $e) {
            $context->setCurrentStep(FileCreationStep::FAILED);
            $context->setErrorMessage("Stub template not found at: {$stubPath}");

            return '';
        }
    }

    /**
     * Replace all placeholders in content with their corresponding values.
     *
     * @param  string  $content  Original content with placeholders
     * @param  ReplacementCollection  $replacements  Collection of placeholder-value pairs
     * @return string Content with all placeholders replaced
     */
    private function replaceVariables(string $content, ReplacementCollection $replacements): string
    {
        $placeholders = $replacements->getPlaceholders()->toArray();
        $values = $replacements->getValues()->toArray();

        return str_replace($placeholders, $values, $content);
    }

    /**
     * Write content to a file.
     *
     * @param  string  $destinationPath  Path where the file should be created
     * @param  string  $content  Content to write to the file
     * @param  FileCreationContext  $context  Context to track any errors
     * @return bool True on success, false on failure
     */
    private function writeFile(string $destinationPath, string $content, FileCreationContext $context): bool
    {
        if ($this->filesystem->put($destinationPath, $content) === false) {
            $context->setCurrentStep(FileCreationStep::FAILED);
            $context->setErrorMessage("Cannot create file: {$destinationPath}");

            return false;
        }

        return true;
    }

    /**
     * Build the destination file path from base directory and path segments.
     *
     * @param  string  $baseDirectory  Base directory relative to working directory
     * @param  PathSegmentsRecord  $segments  Path segments record
     * @return string Complete destination file path
     */
    private function buildDestinationPath(string $baseDirectory, PathSegmentsRecord $segments): string
    {
        $workingDir = rtrim($this->config->workingDirectory(), '/');
        $directory = rtrim($workingDir . $baseDirectory, '/');

        if ($segments->subPath !== '') {
            $directory .= '/' . $segments->subPath;
        }

        return $directory . '/' . $segments->className . '.php';
    }

    /**
     * Build a generic path from base directory and path segments.
     *
     * @param  string  $baseDirectory  Base directory relative to working directory
     * @param  PathSegmentsRecord  $segments  Path segments record
     * @return string Complete path
     */
    private function buildPath(string $baseDirectory, PathSegmentsRecord $segments): string
    {
        $workingDir = rtrim($this->config->workingDirectory(), '/');
        $directory = rtrim($workingDir . $baseDirectory, '/');

        if ($segments->subPath !== '') {
            $directory .= '/' . $segments->subPath;
        }

        return $directory . '/' . $segments->className . '.php';
    }

    /**
     * Create a StringTypedCollection from an array of strings.
     *
     * @param  array<string>  $items  Items to add to the collection
     * @return StringTypedCollection Collection containing the items
     */
    private function createStringCollection(array $items): StringTypedCollection
    {
        $collection = new StringTypedCollection;

        foreach ($items as $item) {
            $collection->add($item);
        }

        return $collection;
    }

    /**
     * Create a collection of PascalCase converted path segments.
     *
     * @param  array<string>  $segments  Original path segments
     * @param  FileCreationContext  $context  Context for transformation logging
     * @return StringTypedCollection Collection of PascalCase segments
     */
    private function createPascalCaseSegments(array $segments, FileCreationContext $context): StringTypedCollection
    {
        $pascalSegments = new StringTypedCollection;

        foreach ($segments as $segment) {
            $pascalSegments->add($this->toPascalCase($segment, $context));
        }

        return $pascalSegments;
    }

    /**
     * Log the variable replacement operation in the context.
     *
     * @param  ReplacementCollection  $replacements  Collection of replacements
     * @param  FileCreationContext  $context  Context to add the log entry
     */
    private function logVariableReplacement(ReplacementCollection $replacements, FileCreationContext $context): void
    {
        $context->addTransformationLog(
            'replaceVariables',
            'replacing ' . $replacements->getPlaceholders()->count() . ' placeholders',
            'done'
        );
    }

    /**
     * Create a failure result record.
     *
     * @param  string  $destinationPath  Destination file path
     * @param  string  $message  Error message
     * @return FileCreationResultRecord Failure result record
     */
    private function createFailureResult(string $destinationPath, string $message): FileCreationResultRecord
    {
        return new FileCreationResultRecord(
            success: false,
            destinationPath: $destinationPath,
            message: $message,
        );
    }
}
