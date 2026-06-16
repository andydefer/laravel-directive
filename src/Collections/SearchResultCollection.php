<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\PhpSearch\Records\SearchResultRecord;

/**
 * Collection typée pour les résultats de recherche.
 * 
 * @extends AbstractTypedCollection<SearchResultRecord>
 * 
 * @author Andy Defer
 */
final class SearchResultCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SearchResultRecord::class);
    }

    public function sortByScore(): self
    {
        $items = $this->items;

        usort($items, function (SearchResultRecord $a, SearchResultRecord $b) {
            return $b->score <=> $a->score;
        });

        $collection = new self();
        foreach ($items as $item) {
            $collection->add($item);
        }

        return $collection;
    }

    public function sortByPercentage(): self
    {
        $items = $this->items;

        usort($items, function (SearchResultRecord $a, SearchResultRecord $b) {
            return $b->percentage <=> $a->percentage;
        });

        $collection = new self();
        $collection->add(...$items);

        return $collection;
    }

    public function getTop(int $limit): self
    {
        $items = array_slice($this->items, 0, $limit);

        $collection = new self();
        foreach ($items as $item) {
            $collection->add($item);
        }

        return $collection;
    }

    public function getFiles(): array
    {
        $files = [];
        foreach ($this->items as $item) {
            $files[] = $item->file_path;
        }
        return array_unique($files);
    }
}
