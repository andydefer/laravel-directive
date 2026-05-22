<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Directive\Unit\Tasks;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Tests\TestCase;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Tasks\DisplayTableTask;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class DisplayTableTaskTest extends TestCase
{
    private DisplayTableTask $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->task = new DisplayTableTask();
    }

    public function test_execute_displays_table_with_headers_and_rows(): void
    {
        // Arrange
        $headers = new StringTypedCollection();
        $headers->add('Name', 'Email', 'Age');

        $rows = new RowCollection();

        $row1 = new RowCollection();
        $row1->add('John Doe', 'john@example.com', 30);
        $rows->add($row1);

        $row2 = new RowCollection();
        $row2->add('Jane Smith', 'jane@example.com', 25);
        $rows->add($row2);

        $record = new DisplayTableRecord($headers, $rows);

        // Act
        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        // Assert - vérifier que les en-têtes sont présents
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Email', $output);
        $this->assertStringContainsString('Age', $output);

        // Vérifier que les données sont présentes
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('john@example.com', $output);
        $this->assertStringContainsString('Jane Smith', $output);
        $this->assertStringContainsString('jane@example.com', $output);

        // Vérifier que le séparateur est présent
        $this->assertStringContainsString('|---', $output);
    }

    public function test_execute_handles_empty_rows(): void
    {
        // Arrange
        $headers = new StringTypedCollection();
        $headers->add('Name', 'Email');

        $rows = new RowCollection();

        $record = new DisplayTableRecord($headers, $rows);

        // Act
        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        // Assert - vérifier que les en-têtes sont présents
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Email', $output);

        // Vérifier que le séparateur est présent
        $this->assertStringContainsString('|---', $output);
    }

    public function test_execute_handles_single_row(): void
    {
        // Arrange
        $headers = new StringTypedCollection();
        $headers->add('Name', 'Email');

        $rows = new RowCollection();

        $row = new RowCollection();
        $row->add('John Doe', 'john@example.com');
        $rows->add($row);

        $record = new DisplayTableRecord($headers, $rows);

        // Act
        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('john@example.com', $output);
    }

    public function test_execute_handles_mixed_data_types(): void
    {
        // Arrange
        $headers = new StringTypedCollection();
        $headers->add('Name', 'Age', 'Active');

        $rows = new RowCollection();

        $row1 = new RowCollection();
        $row1->add('John Doe', 30, true);
        $rows->add($row1);

        $row2 = new RowCollection();
        $row2->add('Jane Smith', 25, false);
        $rows->add($row2);

        $record = new DisplayTableRecord($headers, $rows);

        // Act
        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('30', $output);
        $this->assertStringContainsString('Jane Smith', $output);
        $this->assertStringContainsString('25', $output);
    }

    public function test_execute_handles_special_characters(): void
    {
        // Arrange
        $headers = new StringTypedCollection();
        $headers->add('Message');

        $rows = new RowCollection();

        $row1 = new RowCollection();
        $row1->add('Hello World!');
        $rows->add($row1);

        $row2 = new RowCollection();
        $row2->add('Special chars: @#$%');
        $rows->add($row2);

        $record = new DisplayTableRecord($headers, $rows);

        // Act
        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('Hello World!', $output);
        $this->assertStringContainsString('Special chars: @#$%', $output);
    }

    public function test_execute_handles_null_values(): void
    {
        // Arrange
        $headers = new StringTypedCollection();
        $headers->add('Name', 'Email', 'Phone');

        $rows = new RowCollection();

        $row = new RowCollection();
        $row->add('John Doe', 'john@example.com', null);
        $rows->add($row);

        $record = new DisplayTableRecord($headers, $rows);

        // Act
        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('john@example.com', $output);
    }

    public function test_execute_handles_nested_collections(): void
    {
        // Arrange
        $headers = new StringTypedCollection();
        $headers->add('Name', 'Tags');

        $rows = new RowCollection();

        $tags = new StringTypedCollection();
        $tags->add('php', 'laravel', 'vip');

        $row = new RowCollection();
        $row->add('John Doe', $tags);
        $rows->add($row);

        $record = new DisplayTableRecord($headers, $rows);

        // Act
        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('php, laravel, vip', $output);
    }

    public function test_execute_handles_unicode_characters(): void
    {
        // Arrange
        $headers = new StringTypedCollection();
        $headers->add('Nom', 'Ville');

        $rows = new RowCollection();

        $row1 = new RowCollection();
        $row1->add('Jean François', 'Paris');
        $rows->add($row1);

        $row2 = new RowCollection();
        $row2->add('Marie Curie', 'Varsovie');
        $rows->add($row2);

        $record = new DisplayTableRecord($headers, $rows);

        // Act
        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('Jean François', $output);
        $this->assertStringContainsString('Paris', $output);
        $this->assertStringContainsString('Marie Curie', $output);
        $this->assertStringContainsString('Varsovie', $output);
    }
}
