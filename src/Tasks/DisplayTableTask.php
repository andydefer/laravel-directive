<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tasks;

use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Collections\WidthCollection;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

class DisplayTableTask
{
    public function execute(DisplayTableRecord $record): void
    {
        $widths = $this->calculateColumnWidths($record);

        if ($widths->isEmpty()) {
            return;
        }

        echo $this->formatHeaderRow($record->headers, $widths) . "\n";
        echo $this->formatSeparator($widths) . "\n";

        foreach ($record->rows as $row) {
            echo $this->formatDataRow($row, $widths) . "\n";
        }
    }

    private function calculateColumnWidths(DisplayTableRecord $record): WidthCollection
    {
        $widths = new WidthCollection();
        $headersArray = $record->headers->toArray();
        $columnCount = count($headersArray);

        if ($columnCount === 0) {
            return $widths;
        }

        // Initialize widths with header lengths
        for ($i = 0; $i < $columnCount; $i++) {
            $header = $headersArray[$i] ?? '';
            $widths->add(strlen($header));
        }

        // Update widths based on rows data
        foreach ($record->rows as $row) {
            for ($i = 0; $i < $columnCount; $i++) {
                $value = $row->get($i);
                $length = $this->getValueLength($value);
                $currentWidth = $widths->get($i) ?? 0;

                if ($length > $currentWidth) {
                    $widths->set($i, $length);
                }
            }
        }

        return $widths;
    }

    private function getValueLength(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        if ($value instanceof TypedCollection) {
            return strlen(implode(', ', $value->toArray()));
        }

        return strlen((string) $value);
    }

    private function formatHeaderRow(StringTypedCollection $headers, WidthCollection $widths): string
    {
        $parts = [];
        $headersArray = $headers->toArray();

        foreach ($widths as $index => $width) {
            $header = $headersArray[$index] ?? '';
            $parts[] = str_pad($header, $width);
        }

        return '| ' . implode(' | ', $parts) . ' |';
    }

    private function formatDataRow(RowCollection $row, WidthCollection $widths): string
    {
        $parts = [];

        foreach ($widths as $index => $width) {
            $value = $row->get($index);
            $formattedValue = $this->formatValue($value);
            $parts[] = str_pad($formattedValue, $width);
        }

        return '| ' . implode(' | ', $parts) . ' |';
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof TypedCollection) {
            return implode(', ', $value->toArray());
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function formatSeparator(WidthCollection $widths): string
    {
        $parts = [];

        foreach ($widths as $width) {
            $parts[] = str_repeat('-', $width);
        }

        return '|-' . implode('-|-', $parts) . '-|';
    }
}
