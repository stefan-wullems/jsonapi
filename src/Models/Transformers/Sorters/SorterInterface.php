<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models\Transformers\Sorters;

use Illuminate\Http\Request;

interface SorterInterface
{
    /**
     * SorterInterface constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request);

    /**
     * Check if the given value needs to be sorted
     *
     * @param string $value
     * @return bool
     */
    public function hasSort(string $value): bool;

    /**
     * Get the column name to sort the given value by
     *
     * @param string $value
     * @return string
     */
    public function getColumn(string $value): string;

    /**
     * Get
     * @param string $columnName
     * @return string ASC|DESC
     */
    public function getDirection(string $columnName): string;
}
