<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Concerns;

use Proglum\JsonApi\Http\Exceptions\ClientGeneratedIdException;
use Proglum\JsonApi\Http\Exceptions\InvalidRequestException;
use Proglum\JsonApi\Http\Exceptions\ValidationException;
use App\Log;
use Proglum\JsonApi\Models\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Factory as ValidatorFactory;

trait ValidatesParameters
{
    /**
     * @return array
     */
    abstract public function validationRules(): array;

    /**
     * @return string
     */
    abstract protected function resourceName(): string;

    /**
     * Throws an exception if data.type doesn't match or is not present.
     *
     * @param Request $request
     * @param null|string $type
     * @param array|null $data
     */
    protected function requireType(Request $request, ?string $type = null, ?array $data = null): void
    {
        Log::debug(get_class($this) . ' - requireType()');
        // Ensure the data attribute is present
        $data = $data ?: $request->input('data');

        if (!isset($data)) {
            Log::debug('ValidationException - The data attribute is required.');
            throw new ValidationException('The data attribute is required.');
        }

        // The members data and errors MUST NOT coexist in the same document.
        if (null !== $request->input('errors')) {
            Log::debug('ValidationException - The members data and errors MUST NOT coexist in the same document.');
            throw new ValidationException('The members data and errors MUST NOT coexist in the same document.');
        }

        if (!isset($data['type'])) {
            Log::debug('ValidationException - The data attribute must have a type property.');
            throw new ValidationException('The data attribute must have a type property.');
        }

        if (!is_string($data['type'])) {
            Log::debug('ValidationException - The value of the type member MUST be a string.');
            throw new ValidationException('The value of the type member MUST be a string.');
        }

        if (!isset($type)) {
            $type = $this->resourceName();
        }

        if ((string) $data['type'] !== (string) $type) {
            Log::debug('ValidationException - The type property doesn\'t match the endpoint.');
            throw new ValidationException('The type property doesn\'t match the endpoint.');
        }
    }

    /**
     * Throws an exception if data.id doesn't match or is not present.
     *
     * @param Request $request
     * @param $id
     */
    protected function requireId(Request $request, $id): void
    {
        Log::debug(get_class($this) . ' - requireId()');

        // Ensure the data attribute is present
        $data = $request->input('data');

        if (!isset($data)) {
            throw new ValidationException('The data attribute is required.');
        }

        if (!isset($data['id'])) {
            throw new ValidationException('The data attribute must have an id property.');
        }

        if (!is_string($data['id'])) {
            throw new ValidationException('The value of the id member MUST be a string.');
        }

        if ((string) $data['id'] !== (string) $id) {
            throw new ValidationException('The id property doesn\'t match the endpoint.');
        }
    }

    /**
     * Throws an exception if data.id and data.type don't match or are not present.
     *
     * @param Request $request
     * @param $id
     */
    protected function requireTypeAndId(Request $request, $id): void
    {
        Log::debug(get_class($this) . ' requireTypeAndId()');
        $this->requireType($request);
        $this->requireId($request, $id);
    }

    /**
     * Throws an exception if data.id is present.
     *
     * @param Request $request
     */
    protected function forbidId(Request $request): void
    {
        // Ensure the data attribute is present
        $data = $request->input('data');

        if (isset($data) && isset($data['id'])) {
            throw new ClientGeneratedIdException('Client generated IDs are not allowed.');
        }
    }

    /**
     * Throws an exception if attributes are present which have no corresponding validation rules.
     *
     * @param array $attributes
     */
    protected function preValidate(array $attributes): void
    {
        // Ensure no extra parameters are present
        $extraParameters = array_diff_key($attributes, $this->validationRules());
        if (count($extraParameters)) {
            throw new InvalidRequestException('The ' . key($extraParameters) . ' field is not allowed here.');
        }
    }

    /**
     * Validate the given request with the given rules. If a resource is given, prepare the rules using
     * this resources values. If the resource is not set, clean the rules.
     *
     * @param array $attributes
     * @param array $relationships
     * @param Model|null $resource
     * @return void
     */
    protected function validateOrFail(array $attributes, array $relationships = [], ?Model $resource = null)
    {
        Log::debug(get_class($this) . ' - validateOrFail()');

        /** @var ValidatorFactory $factory */
        $factory = app('validator');
        $rules = $this->prepareRules($resource);
        $attributes = $this->appendRelationships($attributes, $relationships);

        Log::debug('Validating attributets...');

        $validator = $factory->make($attributes, $rules);

        if ($validator->fails()) {
            Log::info($validator->getMessageBag()->first(), ['rules' => $rules]);
            throw new ValidationException($validator->getMessageBag()->first());
        }

        Log::debug('Pass validation');
    }

    /**
     * Throws exceptions if relationship entry has an incorrect format.
     *
     * @param Model $resource
     * @param string $relationshipType
     * @param array|null|bool $data If set to false, no validation on the data is made
     */
    protected function validateRelationships(Model $resource, string $relationshipType, $data = false): void
    {
        Log::debug(get_class($this) . ' - validateRelationships()');

        if (!$resource::isRelation($relationshipType)) {
            $errorMessage = 'Related resource ' . $relationshipType . ' is invalid for this endpoint.';
            Log::info($errorMessage);
            throw new ValidationException($errorMessage);
        }

        if ($data !== false) {
            if (!isset($data)) {
                $errorMessage = 'The data attribute of the ' . $relationshipType . ' relationship is required.';
                Log::info($errorMessage);
                throw new ValidationException($errorMessage);
            }

            foreach ($data as $record) {
                // Type must be set
                if (!isset($record['type'])) {
                    $errorMessage = 'The data attribute of the ' . $relationshipType
                        . ' relationship must have a type property.';
                    Log::info(get_class($this) . ' - validateRelationships() - throw exception', [
                        'errorMessage' => $errorMessage,
                    ]);
                    throw new ValidationException($errorMessage);
                }
                // Type must be a string
                if (!is_string($record['type'])) {
                    $errorMessage = 'The type attribute of the ' . $relationshipType
                        . ' relationship must be a string.';
                    Log::info(get_class($this) . ' - validateRelationships() - throw exception', [
                        'errorMessage' => $errorMessage,
                    ]);
                    throw new ValidationException($errorMessage);
                }

                // This is probably a buggy implementation. Is it in the specs that a relationship type should be
                // the same as the resource type? i.e. how about parent / children types?
                // if ((string) $record['type'] !== str_singular($relationshipType)) {
                //    throw new ValidationException('The type property of the ' . $relationshipType
                //      . ' relationship doesn\'t match the relationships key.');
                // }
                // @todo Create a new check which actually checks the types of the records against a known resource
                // type of the relationship.

                // ID must be set
                if (!isset($record['id'])) {
                    $errorMessage = 'The id attribute of the ' . $relationshipType . ' relationship is required.';
                    Log::info(get_class($this) . ' - validateRelationships() - throw exception', [
                        'errorMessage' => $errorMessage,
                    ]);
                    throw new ValidationException($errorMessage);
                }
                // ID must be a string
                if (!is_string($record['id'])) {
                    $errorMessage = 'The id attribute of the ' . $relationshipType . ' relationship must be a string.';
                    Log::info(get_class($this) . ' - validateRelationships() - throw exception', [
                        'errorMessage' => $errorMessage,
                    ]);
                    throw new ValidationException($errorMessage);
                }
            }
        }
    }

    /**
     * @param Model|null $resource
     * @param bool $allowPartialFieldset If set to true and resource is provided, prefix all rules with `sometimes`.
     * @return array
     */
    private function prepareRules(?Model $resource = null, bool $allowPartialFieldset = true): array
    {
        Log::debug(get_class($this) . ' - prepareRules()');

        $rules = $this->validationRules();
        $rules = $this->prepareEntityId($rules, $resource);

        if (isset($resource) && $allowPartialFieldset === true) {
            $rules = $this->makeRulesOptional($rules);
        }

        return $rules;
    }

    /**
     * In rules, sometimes we'll find an :id: string, which
     * needs to be replaced with the given models key. If no
     * model is given to this method, we'll delete the string
     * from the rules.
     *
     * @param array $rules
     * @param Model|null $resource
     * @return array
     */
    private function prepareEntityId(array $rules, ?Model $resource = null): array
    {
        foreach ($rules as &$rule) {
            // Only replace :id: when rule is in string format
            if (is_string($rule)) {
                if (isset($resource)) {
                    $rule = str_replace(':id:', ',' . $resource->getKey(), $rule);
                } else {
                    $rule = str_replace(':id:', '', $rule);
                }
            }
        }

        return $rules;
    }

    /**
     * @param array $rules
     * @return array
     */
    private function makeRulesOptional(array $rules): array
    {
        foreach ($rules as &$rule) {
            // Only make rule optional when rule is in string format
            if (is_string($rule)) {
                $rule = 'sometimes|' . $rule;
            }
        }

        return $rules;
    }

    /**
     * @param array $attributes
     * @param array|null $relationships
     * @return array
     */
    private function appendRelationships(array $attributes, ?array $relationships): array
    {
        Log::debug(get_class($this) . ' - appendRelationships()');

        foreach ($relationships as $relationshipType => $relationshipData) {
            $relationshipData = Arr::get($relationshipData, 'data', []);

            if (is_null($relationshipData)) {
                continue;
            }

            if (array_is_assoc($relationshipData)) {
                $attributes[$relationshipType . '_id'] = $relationshipData['id'] ?? null;
            } else {
                $attributes[$relationshipType . '_id'] = [];
                foreach ($relationshipData as $relationship) {
                    $attributes[$relationshipType . '_id'][] = $relationship['id'] ?? null;
                }
            }
        }

        return $attributes;
    }
}
