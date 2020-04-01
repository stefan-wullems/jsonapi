<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint;

use Proglum\JsonApi\Endpoint\Concerns\HandlesRelationships;
use Proglum\JsonApi\Endpoint\Concerns\HandlesResources;
use Proglum\JsonApi\Endpoint\Concerns\RespondsWithJsonApi;
use Proglum\JsonApi\Endpoint\Contracts\Endpoint;
use Proglum\JsonApi\Endpoint\Contracts\RestEndpoint as RestEndpointInterface;
use Proglum\JsonApi\Models\Serializers\JsonApiSerializer;
use Proglum\JsonApi\Models\Transformers\AbstractTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use League\Fractal\Serializer\SerializerAbstract;
use League\Fractal\TransformerAbstract;

abstract class RestEndpoint implements Endpoint, RestEndpointInterface
{
    use HandlesResources;
    use HandlesRelationships;
    use RespondsWithJsonApi;

    /** @var string "AbstractTransformer" name */
    protected $transformer;

    /** @var string "SerializerAbstract" name */
    protected $serializer = JsonApiSerializer::class;

    /** @var Request */
    protected $request;

    /** @var array */
    protected $validationRules = [];

    /** @var string "\Proglum\JsonApi\Models\Model" name */
    protected static $model;

    /**
     * RestEndpoint constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get request
     *
     * @return Request
     */
    protected function getRequest()
    {
        return $this->request;
    }

    /**
     * @param string $transformer Name of TransformerAbstract class
     */
    public function useTransformer(string $transformer)
    {
        if (!is_subclass_of($transformer, TransformerAbstract::class)) {
            throw new InvalidArgumentException('$transformer should implement TransformerAbstract.');
        }

        $this->transformer = $transformer;
    }

    /**
     * @param string $serializer Name of SerializerAbstract class
     */
    public function useSerializer(string $serializer)
    {
        if (!is_subclass_of($serializer, SerializerAbstract::class)) {
            throw new InvalidArgumentException('$serializer should implement SerializerAbstract.');
        }

        $this->serializer = $serializer;
    }

    /**
     * @return string
     */
    protected function version(): string
    {
        $currentPath = $this->getRequest()->getPathInfo();

        // First part of path should be the version
        return current(explode('/', trim($currentPath, '/')));
    }

    /**
     * @return string
     */
    protected function baseUrl(): string
    {
        return url($this->version());
    }

    /**
     * @return string
     */
    public function model(): string
    {
        return static::$model;
    }

    /**
     * @return Builder
     */
    protected function query(): Builder
    {
        /** @var Model $model */
        $model = $this->model();

        return $model::query();
    }

    /**
     * @return AbstractTransformer
     */
    protected function transformer(): AbstractTransformer
    {
        /** @var AbstractTransformer $transformer */
        $transformer = app()->make($this->transformer);
        return $transformer;
    }

    /**
     * @return SerializerAbstract
     */
    protected function serializer(): SerializerAbstract
    {
        $serializerClass = $this->serializer;

        return new $serializerClass($this->baseUrl());
    }

    /**
     * @return array
     */
    protected function validationRules(): array
    {
        return $this->validationRules;
    }
}
