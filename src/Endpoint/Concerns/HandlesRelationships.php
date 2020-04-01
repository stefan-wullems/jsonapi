<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Concerns;

use Proglum\JsonApi\Endpoints\Factory as EndpointFactory;
use Proglum\JsonApi\Endpoints\RestEndpoint;
use Proglum\JsonApi\Http\Exceptions\InvalidRequestException;
use Proglum\JsonApi\Http\Exceptions\ValidationException;
use Proglum\JsonApi\Models\Model;
use Proglum\JsonApi\Models\Serializers\JsonApiRelationshipSerializer;
use Proglum\JsonApi\Models\Transformers\RelationTransformer;
use App\Log;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait HandlesRelationships
{
    /**
     * @return string
     */
    abstract public function model(): string;

    /**
     * @return Builder
     */
    abstract protected function query(): Builder;

    /**
     * @param Exception $exception
     * @return Response
     */
    abstract public function errorResponse(Exception $exception);

    /**
     * @param Request $request
     * @param null|string $type
     * @param array|null $data
     */
    abstract protected function requireType(Request $request, ?string $type = null, ?array $data = null): void;

    /**
     * @param Model $resource
     * @param string $type
     * @param array|null $relationships
     * @return mixed
     * @uses \App\Endpoints\Concerns\SavesRelationships::addRelationship()
     */
    abstract public function addRelationship(Model $resource, string $type, ?array $relationships = []);

    /**
     * @param Model $resource
     * @param string $type
     * @uses \App\Endpoints\Concerns\SavesRelationships::truncateRelationship()
     */
    abstract public function truncateRelationship(Model $resource, string $type);

    /**
     * @param Model $resource
     * @param string $type
     * @param array|null $relationships
     * @uses \App\Endpoints\Concerns\SavesRelationships::removeRelationship()
     */
    abstract public function removeRelationship(Model $resource, string $type, ?array $relationships = []);

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function related(Request $request, $id, $type): Response
    {
        try {
            /** @var Relation $relation */
            $relation = $this->getRelation($id, $type);

            // Endpoint can have a different name as the given type for this relationship.
            /** @var Model $relatedModel */
            /** @var RestEndpoint $endpoint */
            $relatedModel = $relation->getRelated();
            $endpoint = EndpointFactory::create($this->version($request), $relatedModel);

            // Different responses for different relation types
            if ($relation instanceof HasOneOrMany || $relation instanceof BelongsToMany) {
                // to-many
                $relatedQuery = $relation->getQuery();

                return $endpoint->index($request, $relatedQuery);
            } elseif ($relation instanceof BelongsTo) {
                // to-one
                return $endpoint->show($request, $relation->getParent()->getAttribute($relation->getForeignKey()));
            }
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * @todo merge with self::related()
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function listRelationship(Request $request, $id, $type): Response
    {
        try {
            /** @var Relation $relation */
            $relation = $this->getRelation($id, $type);

            // Endpoint can have a different name as the given type for this relationship.
            /** @var Model $relatedModel */
            /** @var RestEndpoint $endpoint */
            $relatedModel = $relation->getRelated();
            $endpoint = EndpointFactory::create($this->version(), $relatedModel);

            $endpoint->useTransformer(RelationTransformer::class);
            $endpoint->useSerializer(JsonApiRelationshipSerializer::class);

            // Different responses for different relation types
            if ($relation instanceof HasOneOrMany || $relation instanceof BelongsToMany) {
                // to-many
                $relatedQuery = $relation->getQuery();
                $relatedQuery->select($relation->getRelated()->getQualifiedKeyName());

                return $endpoint->index($request, $relatedQuery);
            } elseif ($relation instanceof BelongsTo) {
                // to-one
                return $endpoint->show($request, $relation->getParent()->getAttribute($relation->getForeignKeyName()));
            }
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function storeRelationship(Request $request, $id, $type): Response
    {
        try {
            /** @var Model $resource */
            $resource = $this->query()->findOrFail($id);

            $this->addRelationship($resource, $type, $request->input('data'));

            $resource->save();

            return $this->listRelationship($request, $id, $type);
        } catch (QueryException $exception) {
            $exception = new ValidationException('Can\'t update relationship, is it required/valid?');

            return $this->errorResponse($exception);
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function updateRelationship(Request $request, $id, $type): Response
    {
        Log::debug(get_class($this) . ' -  updateRelationship()');

        try {
            /** @var Model $resource */
            $resource = $this->query()->findOrFail($id);

            $this->truncateRelationship($resource, $type);
            $this->addRelationship($resource, $type, $request->input('data'));

            $resource->save();

            return $this->listRelationship($request, $id, $type);
        } catch (QueryException $exception) {
            $exception = new ValidationException('Can\'t update relationship, is it required/valid?');

            return $this->errorResponse($exception);
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function destroyRelationship(Request $request, $id, $type): Response
    {
        try {
            /** @var Model $resource */
            $resource = $this->query()->findOrFail($id);

            /** @uses \App\Endpoints\Concerns\SavesRelationships::removeRelationship() */
            $this->removeRelationship($resource, $type, $request->input('data'));

            $resource->save();

            return $this->deletedResponse();
        } catch (QueryException $exception) {
            $exception = new ValidationException('Can\'t update relationship, is it required/valid?');

            return $this->errorResponse($exception);
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * @param $id
     * @param string $type
     * @return Relation
     */
    private function getRelation($id, string $type): Relation
    {
        // Initialize new model to see if related type is valid
        /** @var Model $model */
        $model = $this->model();

        if (!$model::isRelation($type)) {
            throw new InvalidRequestException('Related resource ' . $type . ' is invalid for this endpoint.');
        }

        $resource = $this->query()->findOrFail($id);

        /** @var Relation $relation */
        return $resource->$type();
    }
}
