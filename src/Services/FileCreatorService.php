<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contexts\FileCreationContext;
use AndyDefer\Directive\Contracts\Configs\FileCreatorConfigInterface;
use AndyDefer\Directive\Enums\FileCreationStep;
use AndyDefer\Directive\Records\FileCreationResultRecord;
use AndyDefer\Directive\Records\PathSegmentsRecord;
use AndyDefer\Directive\Records\ReplacementRecord;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;

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
    public function __construct(
        private readonly FileCreatorConfigInterface $config,
        private readonly FileSystemInterface $filesystem,
        private readonly PathSegmentsParserService $pathParser,
        private readonly PathBuilderService $pathBuilder,
        private readonly StringCaseConverterService $caseConverter
    ) {}

    /**
     * Create a file from a stub template with variable replacement.
     *
     * @param string $stubPath Path to the stub template file
     * @param string $destinationPath Absolute path where the file should be created
     * @param ReplacementCollection $replacements Collection of placeholder-value pairs
     * @param FileCreationContext $context Context tracking the creation state
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
        if ($this->filesystem->exists($destinationPath) && !$context->shouldForce()) {
            $context->setCurrentStep(FileCreationStep::FAILED);
            $context->setErrorMessage("File already exists: {$destinationPath}");

            return $this->createFailureResult($destinationPath, "File already exists: {$destinationPath}");
        }

        // Create directory
        $context->setCurrentStep(FileCreationStep::CREATING_DIRECTORY);
        if (!$this->ensureDirectoryExists(dirname($destinationPath), $context)) {
            return $this->createFailureResult($destinationPath, $context->getErrorMessage());
        }

        // Read stub
        $context->setCurrentStep(FileCreationStep::READING_STUB);
        $stubContent = $this->getStubContent($stubPath, $context);
        if ($context->hasError()) {
            return $this->createFailureResult($destinationPath, $context->getErrorMessage());
        }

        // Replace variables
        $context->setCurrentStep(FileCreationStep::REPLACING_VARIABLES);
        $this->logVariableReplacement($replacements, $context);
        $content = $this->replaceVariables($stubContent, $replacements);

        // Write file
        $context->setCurrentStep(FileCreationStep::WRITING_FILE);
        if (!$this->writeFile($destinationPath, $content, $context)) {
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
     * @param string $stubPath Path to the stub template file
     * @param string $name Name used to build the destination path (supports subdirectories like "Admin/UserTask")
     * @param string $baseDirectory Base directory where the file should be created
     * @param ReplacementCollection $replacements Collection of placeholder-value pairs
     * @param FileCreationContext $context Context tracking the creation state
     * @return FileCreationResultRecord Result containing success status and details
     */
    public function createFileFromName(
        string $stubPath,
        string $name,
        string $baseDirectory,
        ReplacementCollection $replacements,
        FileCreationContext $context,
    ): FileCreationResultRecord {
        $segments = $this->pathParser->parse($name);
        $destinationPath = $this->pathBuilder->buildFilePath($baseDirectory, $segments);

        // Add useful replacements automatically
        $enhancedReplacements = $this->addAutomaticReplacements(
            $replacements,
            $segments,
            $baseDirectory
        );

        return $this->createFile($stubPath, $destinationPath, $enhancedReplacements, $context);
    }

    /**
     * Get the case converter service for advanced use cases.
     */
    public function getCaseConverter(): StringCaseConverterService
    {
        return $this->caseConverter;
    }

    /**
     * Get the path parser service for advanced use cases.
     */
    public function getPathParser(): PathSegmentsParserService
    {
        return $this->pathParser;
    }

    /**
     * Get the path builder service for advanced use cases.
     */
    public function getPathBuilder(): PathBuilderService
    {
        return $this->pathBuilder;
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $path Directory path to check/create
     * @param FileCreationContext $context Context to track directory creation
     * @return bool True if directory exists or was created, false on error
     */
    private function ensureDirectoryExists(string $path, FileCreationContext $context): bool
    {
        $context->setCurrentDirectory($path);

        if ($this->filesystem->isDirectory($path)) {
            return true;
        }

        $permission = $this->config->directoryPermission();
        $success = $this->filesystem->makeDirectory($path, $permission, true);

        if ($success) {
            $context->addCreatedDirectory($path);
            return true;
        }

        $context->setCurrentStep(FileCreationStep::FAILED);
        $context->setErrorMessage("Cannot create directory: {$path}");
        return false;
    }

    /**
     * Read and return the content of a stub file.
     *
     * @param string $stubPath Path to the stub file
     * @param FileCreationContext $context Context to store the content or error
     * @return string Stub file content, empty string on error
     */
    private function getStubContent(string $stubPath, FileCreationContext $context): string
    {
        if (!$this->filesystem->exists($stubPath)) {
            $context->setCurrentStep(FileCreationStep::FAILED);
            $context->setErrorMessage("Stub template not found at: {$stubPath}");
            return '';
        }

        try {
            $content = $this->filesystem->get($stubPath);
            $context->setStubContent($content);
            return $content;
        } catch (\RuntimeException $e) {
            $context->setCurrentStep(FileCreationStep::FAILED);
            $context->setErrorMessage("Cannot read stub template: {$stubPath}");
            return '';
        }
    }

    /**
     * Replace all placeholders in content with their corresponding values.
     * Handles placeholders with or without spaces (e.g., {{name}} or {{ name }}).
     *
     * @param string $content Original content with placeholders
     * @param ReplacementCollection $replacements Collection of placeholder-value pairs
     * @return string Content with all placeholders replaced
     */
    private function replaceVariables(string $content, ReplacementCollection $replacements): string
    {
        $placeholders = $replacements->getPlaceholders()->toArray();
        $values = $replacements->getValues()->toArray();

        foreach ($placeholders as $index => $placeholder) {
            // Clean the original placeholder (remove {{ and }})
            $clean = trim($placeholder, '{}');
            $clean = trim($clean);

            // Replace all possible variants (with/without spaces)
            $content = str_replace(
                [
                    '{{' . $clean . '}}',
                    '{{ ' . $clean . ' }}',
                    '{{' . $clean . ' }}',
                    '{{ ' . $clean . '}}',
                ],
                $values[$index],
                $content
            );
        }

        return $content;
    }

    /**
     * Write content to a file.
     *
     * @param string $destinationPath Path where the file should be created
     * @param string $content Content to write to the file
     * @param FileCreationContext $context Context to track any errors
     * @return bool True on success, false on failure
     */
    private function writeFile(string $destinationPath, string $content, FileCreationContext $context): bool
    {
        $result = $this->filesystem->put($destinationPath, $content);

        if ($result === false) {
            $context->setCurrentStep(FileCreationStep::FAILED);
            $context->setErrorMessage("Cannot write file: {$destinationPath}");
            return false;
        }

        return true;
    }

    /**
     * Add automatic replacements based on parsed segments.
     *
     * @param ReplacementCollection $replacements Original replacements
     * @param PathSegmentsRecord $segments Parsed path segments
     * @param string $baseDirectory Base directory for the file
     * @return ReplacementCollection Enhanced replacements
     */
    private function addAutomaticReplacements(
        ReplacementCollection $replacements,
        PathSegmentsRecord $segments,
        string $baseDirectory
    ): ReplacementCollection {
        // Add commonly needed replacements automatically
        $autoReplacements = [
            '{{className}}' => $segments->className,
            '{{classBaseName}}' => $segments->className,
            '{{subPath}}' => $segments->subPath,
            '{{kebabClassName}}' => $this->caseConverter->toKebabCase($segments->className),
            '{{snakeClassName}}' => $this->caseConverter->toSnakeCase($segments->className),
        ];

        foreach ($autoReplacements as $placeholder => $value) {
            if (!$replacements->hasPlaceholder($placeholder)) {
                $replacements->add(new ReplacementRecord($placeholder, $value));
            }
        }

        return $replacements;
    }

    /**
     * Log the variable replacement operation in the context.
     *
     * @param ReplacementCollection $replacements Collection of replacements
     * @param FileCreationContext $context Context to add the log entry
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
     * @param string $destinationPath Destination file path
     * @param string $message Error message
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
