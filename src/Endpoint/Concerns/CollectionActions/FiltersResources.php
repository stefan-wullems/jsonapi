<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Concerns\CollectionActions;

use Proglum\JsonApi\Http\Exceptions\InvalidRequestException;
use Proglum\JsonApi\Models\Filter;
use Proglum\JsonApi\Models\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltersResources
{
    /**
     * Query string parameter to read filter columns from.
     *
     * @var string
     */
    protected $filterParameter = 'filter';

    /**
     * @param Builder $query
     */
    protected function filter(Builder $query): void
    {
        /** @var Request $request */
        $request = app('request');

        if ($request->filled($this->filterParameter)) {
            $filters = $request->get($this->filterParameter);

            // Filter empty filters
            $filters = array_filter($filters);

            // Make a mock instance so we can describe its columns
            /** @var Model $model */
            $schema = app('db')->connection()->getSchemaBuilder();
            $model = $query->getModel();
            $table = $model->getTable();
            $columns = $schema->getColumnListing($query->getModel()->getTable());

            // Clean filters
            foreach ($filters as $key => $value) {
                if (!$model->isFilterable($key)) {
                    throw new InvalidRequestException('Can not filter on attribute ' . $key);
                }
            }

            Filter::applyQueryFilters($query, $filters, $columns, $table);
        }
    }
}
