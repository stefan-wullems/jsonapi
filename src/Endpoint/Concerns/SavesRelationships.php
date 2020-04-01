<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Concerns;

use Proglum\JsonApi\Models\Model;
use Proglum\JsonApi\Http\Exceptions\ValidationException;
use App\Log;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;

trait SavesRelationships
{
    /**
     * @param Model $resource
     * @param string $relationshipType
     * @param array|null|bool $data
     */
    abstract protected function validateRelationships(Model $resource, string $relationshipType, $data = false): void;

    /**
     * @param Model $resource
     * @param array $relationships
     */
    public function addRelationships(Model $resource, array $relationships)
    {
        Log::debug(get_class($this) . ' -  addRelationships()');

        foreach ($relationships as $relationshipType => $relationshipData) {
            $relationshipData = Arr::get($relationshipData, 'data');

            $this->addRelationship($resource, $relationshipType, $relationshipData);
        }
    }

    /**
     * Update multiple relationships
     *
     * @param Model $resource
     * @param array $relationshipsCategories Array of JSON api input from the relationship part of the input
     * There is an example of the $relationships input:
     * <code>
     *  [
     *      'children' =>
     *          ['data' => [
     *              ['type' => 'budgetitem', 'id' => '1'],
     *              ['type' => 'budgetitem', 'id' => '2']
     *          ]
     *      ],
     *      'account' => [
     *          'data' => ['type' => 'account', 'id' => '1']
     *      ],
     *      'report' => [...]
     *  ]
     * </code>
     * @return bool
     */
    public function updateRelationships(Model $resource, array $relationshipsCategories): bool
    {
        Log::debug('SavesRelationships -  updateRelationships()', ['count' => count($relationshipsCategories)]);

        if (empty($relationshipsCategories)) {
            Log::debug('No relationships to update');
            return true;
        }

        foreach ($relationshipsCategories as $relationshipCategory => $relationshipCategoryData) {
            // Validate input
            $relation = $resource->$relationshipCategory();
            if ($relation instanceof BelongsTo || $relation instanceof HasOne) {
                if (
                    !empty($relationshipCategoryData['data'])
                    && $this->isMultidimensionalArray($relationshipCategoryData['data'])
                ) {
                    throw new ValidationException($relationshipCategory . ' is a to-one relationship');
                }
                $this->updateToOneRelationship($resource, $relationshipCategory, $relationshipCategoryData['data']);
            } else {
                // To-many relationship
                if (
                    !empty($relationshipCategoryData['data'])
                    && !$this->isMultidimensionalArray($relationshipCategoryData['data'])
                ) {
                    throw new ValidationException($relationshipCategory . ' is a to-many relationship');
                }
                $this->updateToManyRelationship($resource, $relationshipCategory, $relationshipCategoryData);
            }
        }

        Log::debug('Relationships updated.');
        return true;
    }

    protected function updateToOneRelationship(Model $resource, string $relationshipCategory, ?array $data): bool
    {
        Log::debug('SaveRelationships - updateToOneRelationship()', [
            'model' => get_class($resource),
            'relationshipCategory' => $relationshipCategory,
        ]);

        // Validate $dat
        if (!empty($data)) {
            if (!isset($data['type']) || !isset($data['id'])) {
                throw new ValidationException($relationshipCategory . ' expects type and id fields.');
            }
            if (!is_string($data['id'])) {
                throw new ValidationException($relationshipCategory . ' id must be a string.');
            }
        }

        switch (true) {
            case (is_null($resource->$relationshipCategory) && empty($data)):
                // Nothing to update
                Log::debug('Nothing to update.');
                return true;

            case (is_null($resource->$relationshipCategory) && !empty($data)):
                // Adding relationship
                Log::debug('Adding relationship...');
                $relation = $resource->$relationshipCategory();
                $newId = $data['id'];
                return $this->addSingleRelationship($resource, $relation, $newId);

            case (!is_null($resource->$relationshipCategory) && empty($data)):
                Log::debug('Removing relationship...');
                $relation = $resource->$relationshipCategory();
                $existingId = $resource->$relationshipCategory->id;
                return $this->removeSingleRelationship($resource, $relation, $existingId);

            default:
                // Relationship exists && $data is set.
                $existingId = $resource->$relationshipCategory->id;
                $newId = $data['id'];
                if ($existingId == $newId) {
                    // Same, same - nothing to update
                    Log::debug('Same, same - nothing to update');
                    return true;
                }

                $relation = $resource->$relationshipCategory();
                $this->removeSingleRelationship($resource, $relation, $existingId);
                $this->addSingleRelationship($resource, $relation, $newId);
                return true;
        }
    }

    protected function updateToManyRelationship(Model $resource, string $relationshipCategory, array $data): bool
    {
        // Check what needs to be updated
        $currentRelationshipCount = ($resource->$relationshipCategory) ? $resource->$relationshipCategory->count() : 0;
        $newRelationshipCount = (empty($data) ? 0 : count(Arr::get($data, 'data')));

        Log::debug('updateToManyRelationship()', [
            'relationshipCategory' => $relationshipCategory,
            'currentRelationshipCount' => $currentRelationshipCount,
            'newRelationshipCount' => $newRelationshipCount,
        ]);

        switch (true) {
            case $currentRelationshipCount == 0 && $newRelationshipCount == 0:
                Log::debug('No changes needed.');
                // Resource has no relationship entities and input data is empty, so nothing needs to change
                break;
            case $currentRelationshipCount > 0 && $newRelationshipCount == 0:
                Log::debug('Truncating relationships...');
                // Data is empty, but there are relationship resources - truncate the relationship
                $this->truncateRelationship($resource, $relationshipCategory);
                break;
            case $currentRelationshipCount == 0 && $newRelationshipCount > 0:
                Log::debug('Adding new relationships...');
                // No current relationship, but need to save new ones
                $this->addRelationship($resource, $relationshipCategory, Arr::get($data, 'data'));
                break;
            case $currentRelationshipCount > 0 && $newRelationshipCount > 0:
            default:
                Log::debug('Both adding and removal to relationship needed...');
                $this->updateSingleRelationshipCategory($resource, $relationshipCategory, Arr::get($data, 'data'));
                break;
        }

        return true;
    }

    /**
     * Checks is array is multidimensional
     *
     * @param array $input
     * @return bool
     */
    protected function isMultidimensionalArray(array $input)
    {
        return (count($input) == count($input, COUNT_RECURSIVE)) ? false : true;
    }


    /**
     * Update a single relationship
     *
     * @param Model $resource
     * @param string $relationshipType
     * @param array $data
     * @return bool
     */
    protected function updateSingleRelationshipCategory(Model $resource, string $relationshipType, array $data)
    {
        Log::debug('updateSingleRelationshipCategory()', ['type' => $relationshipType]);

        /** @var Model[] $currentRelationships */
        $currentRelationships = $resource->$relationshipType;
        $currentIds = [];
        foreach ($currentRelationships as $related) {
            $currentIds[] = $related->getKey();
        }
        $newIds = [];
        foreach ($data as $relationship) {
            $newIds[] = $relationship['id'];
        }

        if (Arr::sort($currentIds) == Arr::sort($newIds)) {
            Log::debug('Relationship are identical. Nothing to update.');
            return true;
        }

        $removeIds = array_diff($currentIds, $newIds);
        $addIds = array_diff($newIds, $currentIds);

        Log::debug('Relationship changes...', ['add count' => count($addIds), 'remove' => count($removeIds)]);

        foreach ($removeIds as $removeId) {
            $this->removeSingleRelationship($resource, $resource->$relationshipType(), $removeId);
        }
        foreach ($addIds as $addId) {
            $this->addSingleRelationship($resource, $resource->$relationshipType(), $addId);
        }

        return true;
    }

    /**
     * @param Model $resource
     * @param string $type
     * @param array|null $relationships
     */
    public function addRelationship(Model $resource, string $type, ?array $relationships = [])
    {
        Log::debug(get_class($this) . ' -  addRelationship()', [
            'model' => get_class($resource),
            'type' => $type,
        ]);

        if ($relationships == null) {
            return;
        }
        // Normalize relationships data
        if (array_is_assoc($relationships)) {
            $relationships = [$relationships];
        }
        // Validate relationship for this request
        /** @uses \Proglum\JsonApi\Endpoint\Concerns\HandlesResources::validateRelationships() */
        $this->validateRelationships($resource, $type, $relationships);

        // Create relation instance for this resource/type
        /** @var Relation $relation */
        $relation = $resource->$type();

        // Now iterate all relationship data and add to resource
        foreach ($relationships as $relationship) {
            $id = $relationship['id'];

            if (!$id) {
                throw new ValidationException('Resource relationship has no id.');
            }

            if ($relation instanceof BelongsTo) {
                /** @var Model $child */
                $child = $relation->getQuery()->getModel();
                $child = $child->findOrFail($id);

                $relation->associate($child);
            } elseif ($relation instanceof HasOneOrMany) {
                /** @var Model $child */
                $child = $relation->getQuery()->getModel();
                $child = $child->findOrFail($id);

                $relation->save($child);
            } elseif ($relation instanceof BelongsToMany) {
                $relation->attach($id);
            }
        }
    }

    /**
     * @param Model $resource
     * @param string $type
     */
    public function truncateRelationship(Model $resource, string $type)
    {
        Log::debug(get_class($this) . ' -  truncateRelationship()', ['type' => $type]);

        // Validate relationship for this request
        $this->validateRelationships($resource, $type);
        // Create relation instance for this resource/type
        /** @var Relation $relation */
        $relation = $resource->$type();

        // Now truncate
        if ($relation instanceof BelongsTo) {
            $relation->dissociate();
        } elseif ($relation instanceof HasOneOrMany) {
            /** @var Model $model */
            foreach ($relation->getResults() as $model) {
                // We may be setting a field to null here which has a database constraint. Try to
                // detect that, and only truncate if it can be null in the db schema.
                $column = $model->getConnection()
                    ->getDoctrineColumn($model->getTable(), $relation->getForeignKeyName());
                if ($column->getNotnull() !== true) {
                    $model->setAttribute($relation->getForeignKeyName(), null);
                    $model->update();
                }
            }
        } elseif ($relation instanceof BelongsToMany) {
            $relation->detach();
        }
    }

    /**
     * @param Model $resource
     * @param string $type
     * @param array|null $relationships
     * @return bool
     */
    public function removeRelationship(Model $resource, string $type, ?array $relationships = [])
    {
        Log::debug('Removing relationship...');

        // Create relation instance for this resource/type
        /** @var Relation $relation */
        $relation = $resource->$type();

        // Now iterate all relationship data and remove
        foreach ($relationships as $relationship) {

            /** @var array $relationship */
            $id = $relationship['id'];

            if (!$id) {
                throw new ValidationException('Resource relationship has no id.');
            }

            return $this->removeSingleRelationship($resource, $relation, $id);
        }
    }

    /**
     * Remove a relationship
     *
     * @param Model $resource
     * @param Relation $relation
     * @param int|string $removeId
     * @return bool
     */
    protected function removeSingleRelationship(Model $resource, Relation $relation, $removeId): bool
    {
        Log::debug('Removing single relationship...');
        if ($relation instanceof BelongsTo) {
            if ((string) $resource->getAttribute($relation->getForeignKeyName()) === (string) $removeId) {
                $relation->dissociate();
                return true;
            }
        } elseif ($relation instanceof HasOneOrMany) {
            /** @var Model $model */
            $model = $relation->getQuery()->getModel();
            $model = $model::findOrFail($removeId);

            $model->setAttribute($relation->getForeignKeyName(), null);
            $model->update();
        } elseif ($relation instanceof BelongsToMany) {
            $relation->detach($removeId);
        }

        return true;
    }

    /**
     * Add a relationship
     *
     * @param Model $resource
     * @param Relation $relation
     * @param int|string $newId
     * @return bool
     */
    protected function addSingleRelationship(Model $resource, Relation $relation, $newId): bool
    {
        Log::debug('Adding single relationship...');

        if ($relation instanceof BelongsTo) {
            /** @var Model $child */
            $child = $relation->getQuery()->getModel();
            $child = $child->findOrFail($newId);

            $relation->associate($child);
        } elseif ($relation instanceof HasOneOrMany) {
            /** @var Model $child */
            $child = $relation->getQuery()->getModel();
            $child = $child->findOrFail($newId);

            $relation->save($child);
        } elseif ($relation instanceof BelongsToMany) {
            $relation->attach($newId);
        }

        return true;
    }
}
