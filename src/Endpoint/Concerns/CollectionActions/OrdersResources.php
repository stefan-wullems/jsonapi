<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Concerns\CollectionActions;

use Proglum\JsonApi\Http\Exceptions\InvalidRequestException;
use Proglum\JsonApi\Models\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait OrdersResources
{
    /**
     * Query string parameter to read sort column from.
     *
     * @var string
     */
    protected $orderParameter = 'sort';

    /**
     * @param Builder $query
     */
    protected function order(Builder $query): void
    {
        /** @var Request $request */
        $request = app('request');

        if ($request->filled($this->orderParameter)) {
            $sort = $request->get($this->orderParameter);

            // Clean parameter
            /** @var Model $model */
            $model = $query->getModel();
            if (!$model->isFilterable(str_replace('-', '', $sort))) {
                throw new InvalidRequestException('Can not sort on attribute ' . str_replace('-', '', $sort));
            }

            // Add orderBy clause to query
            if (substr($sort, 0, 1) === '-') {
                $query->orderBy(substr($sort, 1), 'desc');
            } else {
                $query->orderBy($sort);
            }
        }
    }
}
