<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Concerns;

use Proglum\JsonApi\Models\Transformers\AbstractTransformer;
use App\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use League\Fractal\Manager;

trait IncludesRelatedEntities
{
    /**
     * @return bool
     */
    abstract protected function paginatesToInfinity(): bool;

    /**
     * @param Builder|Model $queryOrResource
     * @param Manager $fractal
     * @param AbstractTransformer $transformer
     */
    protected function processIncludes($queryOrResource, Manager $fractal, AbstractTransformer $transformer)
    {
        /** @var Request $request */
        $request = app('request');

        $includes = $request->input('include', []);
        Log::debug('IncludesRelatedEntities - processIncludes', [
            'queryOrResource' => get_class($queryOrResource),
            'includes' => $includes,
        ]);

        // If includes are empty, set to an empty array to skip parsing
        if (empty($includes)) {
            $includes = [];
        }

        $fractal->parseIncludes($includes);

        if ($queryOrResource instanceof Builder) {
            // Combine requested and default includes
            $eagerLoads = array_merge($fractal->getRequestedIncludes(), $transformer->getDefaultIncludes());

            // Work around issue https://exivity.atlassian.net/browse/EXVT-1867
            $eagerLoads = $transformer->mapIncludeToRelationship($eagerLoads);

            // Set eager loading on the query.
            // If the query requests infinityPaging, transform the eager loading query by removing the whereIn
            // SQL-clause to work around the Warning: Too many SQL variables error.
            // @todo There seems to be a bug with the eagerLoad custom query for nested relations. If two eagerLoads
            // relations are requested, one of which a nested relation of the other (i.e. include=rates,rates.account)
            // the callback function for the 'main' relation (rates in our example) is not executed, and the whereIn
            // clause remains.
            if ($this->paginatesToInfinity()) {
                foreach ($eagerLoads as $eagerLoad) {
                    $queryOrResource->with([
                      $eagerLoad => function ($query) {
                          /** @var Relation $query */
                          $dbQuery = $query->getQuery()->getQuery();

                          $dbQuery->wheres = [];
                          $dbQuery->setBindings([]);
                      },
                    ]);
                }
            } else {
                $queryOrResource->with($eagerLoads);
            }
        }
    }
}
