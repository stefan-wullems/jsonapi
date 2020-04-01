<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Concerns;

use Proglum\JsonApi\Http\JsonApiResponse;
use Proglum\JsonApi\Models\Model;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Http\Response;

trait RespondsWithJsonApi
{
    use TransformsResources;

    /**
     * @return string
     */
    abstract public function model(): string;

    /**
     * @return string
     */
    abstract public function baseUrl(): string;

    /**
     * @param Builder $query
     * @return JsonApiResponse
     */
    public function listResponse(Builder $query)
    {
        return $this->json($this->transform($query), 200);
    }

    /**
     * @param Builder|EloquentModel $queryOrResource
     * @return JsonApiResponse
     */
    public function showResponse($queryOrResource)
    {
        return $this->json($this->transformOne($queryOrResource), 200);
    }

    /**
     * @param EloquentModel $resource
     * @return JsonApiResponse
     */
    public function createdResponse(EloquentModel $resource)
    {
        $url = $this->baseUrl() . '/' . $this->resourceName() . '/' . $resource->getKey();

        return $this->json($this->transformOne($resource), 201, [
          'Location' => $url,
        ]);
    }

    /**
     * @return Response
     */
    public function deletedResponse()
    {
        return response('', 204);
    }

    /**
     * @param Exception $exception
     * @return JsonApiResponse
     * @throws Exception
     */
    public function errorResponse(Exception $exception)
    {
        // Let our generic Exception Handler facility do the work.
        throw $exception;
    }

    /**
     * @return string
     */
    protected function resourceName(): string
    {
        /** @var Model $model */
        $model = $this->model();

        return $model::resourceName();
    }

    /**
     * @param array|Arrayable $data
     * @param int $status
     * @param array $headers
     * @param int $options
     * @return JsonApiResponse
     */
    protected function json($data = [], int $status = 200, array $headers = [], int $options = 0): JsonApiResponse
    {
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }

        return new JsonApiResponse($data, $status, $headers, $options);
    }
}
