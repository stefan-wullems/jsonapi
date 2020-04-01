<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Concerns;

use Proglum\JsonApi\Models\Model;
use Proglum\JsonApi\Models\Transformers\AbstractTransformer;
use Proglum\JsonApi\Http\Exceptions\InvalidRequestException;
use Proglum\JsonApi\Endpoint\Concerns\CollectionActions\FiltersResources;
use Proglum\JsonApi\Endpoint\Concerns\CollectionActions\OrdersResources;
use App\Log;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait HandlesResources
{
    use ValidatesParameters;
    use OrdersResources;
    use FiltersResources;
    use SavesRelationships;

    /**
     * @return string
     */
    abstract public function model(): string;

    /**
     * @return Builder
     */
    abstract protected function query(): Builder;

    /**
     * @return AbstractTransformer
     */
    abstract protected function transformer(): AbstractTransformer;

    /**
     * @param Builder $query
     * @return Response
     */
    abstract public function listResponse(Builder $query);

    /**
     * @param Builder|EloquentModel $queryOrResource
     * @return Response
     */
    abstract public function showResponse($queryOrResource);

    /**
     * @param EloquentModel $resource
     * @return Response
     */
    abstract public function createdResponse(EloquentModel $resource);

    /**
     * @return Response
     */
    abstract public function deletedResponse();

    /**
     * @param Exception $exception
     * @return Response
     */
    abstract public function errorResponse(Exception $exception);

    /**
     * @param Request $request
     * @param Builder|null $query
     * @return Response
     */
    public function index(Request $request, ?Builder $query = null): Response
    {
        Log::debug(get_class($this) . '- index()');

        if (!isset($query)) {
            // Initialize new query to retrieve resources
            $query = $this->query();
        }

        // Sort & filter
        $this->order($query);
        $this->filter($query);

        try {
            return $this->listResponse($query);
        } catch (QueryException $exception) {
            // If the query has too many variables, we want to return a more helpful message
            if (strpos($exception->getMessage(), 'too many SQL variables') !== false) {
                $e = new InvalidRequestException(
                    'Too many SQL variables in query. Are you using pagination? '
                    . 'Try using page[limit]=-1, as this is designed to work better with large result sets.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $exception
                );
                return $this->errorResponse($e);
            }
            throw $exception;
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function show(Request $request, $id): Response
    {
        Log::debug(get_class($this) . '- show()');

        $query = $this->query()->whereKey($id);

        try {
            return $this->showResponse($query);
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        Log::debug(get_class($this) . '- store()');

        $attributes = $request->input('data.attributes', []);
        $relationships = $request->input('data.relationships', []);

        try {
            $this->requireType($request);

            // Disabled id check until https://gitter.im/orbitjs/orbit.js?at=59e4f31c5c40c1ba79aa9f91 is resolved.
            // @todo Re-enable call to forbidId once above issue is resolved.
            // $this->forbidId($request);

            $this->preValidate($attributes);
            // Creation doesn't need ID
            if (isset($this->validationRules['id'])) {
                unset($this->validationRules['id']);
            }
            $this->validateOrFail($attributes, $relationships);

            $query = $this->query();

            /** @var Model $resource */
            $resource = $query->newModelInstance();

            // Parse attributes
            $transformer = $this->transformer();
            Log::debug(get_class($this) . '- store() - Parse attributes variables', [
                'resource' => get_class($resource),
                'transformer' => get_class($transformer),
            ]);
            $parsedAttributes = $transformer->parse($attributes);

            // Map attributes to database columns
            $mappedAttributes = $transformer->mapApiToModel($parsedAttributes);

            // Save attributes
            $resource->fill($mappedAttributes);

            // Filter out relationships which need a resource id
            $relationshipsDependingOnOwnId = [];
            $relationshipsNotDependingOnOwnId = [];
            foreach ($relationships as $relationshipType => $relationshipData) {
                $type = $resource::getRelationType($relationshipType);

                if (in_array(trim($type, "\\"), [BelongsTo::class])) {
                    $relationshipsNotDependingOnOwnId[$relationshipType] = $relationshipData;
                } else {
                    $relationshipsDependingOnOwnId[$relationshipType] = $relationshipData;
                }
            }

            // Save all relationships which do not need a resource id
            $this->addRelationships($resource, $relationshipsNotDependingOnOwnId);

            // Save resource
            $resource->save();
            $resource->refresh();

            // Save all relationships which need a resource id
            $this->addRelationships($resource, $relationshipsDependingOnOwnId);

            return $this->createdResponse($resource);
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function update(Request $request, $id): Response
    {
        Log::debug(get_class($this) . '- update()', ['id' => $id]);

        $attributes = $request->input('data.attributes', []);
        $relationships = $request->input('data.relationships', []);

        try {
            $this->requireTypeAndId($request, $id);
            $this->preValidate($attributes);

            /** @var Model $resource */
            $resource = $this->query()->findOrFail($id);

            $this->validateOrFail($attributes, $relationships, $resource);

            // Parse attributes
            $transformer = $this->transformer();
            $parsedAttributes = $transformer->parse($attributes);

            // Only save the attributes in the original request ($transformer->parse may have created new keys)
            $attributes = array_intersect_key($parsedAttributes, $attributes);
            // Map attributes to database columns
            $mappedAttributes = $transformer->mapApiToModel($attributes);

            // Save attributes
            $resource->fill($mappedAttributes);

            // Save relationships
            $this->updateRelationships($resource, $relationships);

            // Save resource
            $resource->save();
            $resource->refresh();

            // Reload resource to include any relationship changes
            $resource = $this->query()->findOrFail($id);

            return $this->showResponse($resource);
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function destroy(Request $request, $id): Response
    {
        Log::debug(get_class($this) . '- destroy()');

        try {
            $resource = $this->query()->findOrFail($id);
            Log::debug(get_class($this) . ' - exists. Now delete...');
            $resource->delete();

            Log::debug(get_class($this) . ' - deleted. Now response...');

            return $this->deletedResponse();
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * @param Request $request
     * @param string $column
     * @param $value
     * @return Response
     */
    public function find(Request $request, string $column, $value = null): Response
    {
        Log::debug(get_class($this) . '- find()');

        if (!isset($value)) {
            $value = $request->input($column);
        }

        $query = $this->query()->where($column, '=', $value);

        try {
            return $this->showResponse($query);
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }
}
