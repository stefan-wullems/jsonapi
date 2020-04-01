<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Concerns;

use Proglum\JsonApi\Endpoint\Concerns\CollectionActions\PaginatesResources;
use Proglum\JsonApi\Models\Transformers\AbstractTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use League\Fractal\Manager;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\SerializerAbstract;

trait TransformsResources
{
    use IncludesRelatedEntities;
    use PaginatesResources;

    /**
     * @return AbstractTransformer
     */
    abstract public function transformer(): AbstractTransformer;

    /**
     * @return SerializerAbstract
     */
    abstract public function serializer(): SerializerAbstract;

    /**
     * @return string
     */
    abstract protected function resourceName(): string;

    /**
     * @param Builder|Model $queryOrResource
     * @param bool $singleItem
     * @param bool $paginate
     * @return array
     */
    public function transform($queryOrResource, bool $singleItem = false, bool $paginate = true): array
    {
        // Create Fractal manager instance and set serializer
        $fractal = new Manager();
        $fractal->setSerializer($this->serializer());

        // First create transformer, which will give us a list of includes
        $transformer = $this->transformer();


        // Process includes
        $this->processIncludes($queryOrResource, $fractal, $transformer);

        if ($singleItem === true && $queryOrResource instanceof Builder) {
            // Create a fractal item from query or fail
            $resource = new Item($queryOrResource->firstOrFail(), $transformer, $this->resourceName());
        } elseif ($singleItem === true && $queryOrResource instanceof Model) {
            // Create a fractal item from resource
            $resource = new Item($queryOrResource, $transformer, $this->resourceName());
        } elseif ($singleItem === false && $queryOrResource instanceof Builder) {
            if ($paginate) {
                // Paginate the results
                $paginator = $this->paginate($queryOrResource);

                // Create a fractal collection and set paginator
                $resource = new Collection($paginator->items(), $transformer, $this->resourceName());
                $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
            } else {
                $resource = new Collection($queryOrResource->get(), $transformer, $this->resourceName());
            }
        } else {
            throw new InvalidArgumentException(
                'Can\'t transform a collection when a single resource is given. '
                . 'Provide a query builder instance instead.'
            );
        }

        // Create the data
        return $fractal->createData($resource)->toArray();
    }

    /**
     * @param Builder|Model $queryOrResource
     * @return array
     */
    public function transformOne($queryOrResource): array
    {
        return $this->transform($queryOrResource, true);
    }
}
