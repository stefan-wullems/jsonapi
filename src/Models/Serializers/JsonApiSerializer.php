<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models\Serializers;

use Illuminate\Support\Str;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Serializer\JsonApiSerializer as BaseJsonApiSerializer;

/**
 * Based on League\Fractal\Serializer\JsonApiSerializer
 * That class has some private methods that make it impossible to inherit from, so they're in here too.
 */
class JsonApiSerializer extends BaseJsonApiSerializer
{
    /**
     * Maps a resource type to a URI path.
     *
     * @param string $type
     * @return string
     */
    protected function getTypePath($type)
    {
        return Str::plural($type);
    }

    /**
     * Serialize an item.
     *
     * @param string $resourceKey
     * @param array $data
     *
     * @return array
     */
    public function item($resourceKey, array $data)
    {
        $id = $this->getIdFromData($data);

        $resource = [
            'data' => [
                'type' => $resourceKey,
                'id' => "$id",
                'attributes' => $data,
            ],
        ];

        unset($resource['data']['attributes']['id']);

        if (isset($resource['data']['attributes']['links'])) {
            $custom_links = $data['links'];
            unset($resource['data']['attributes']['links']);
        }

        if (isset($resource['data']['attributes']['meta'])) {
            $resource['data']['meta'] = $data['meta'];
            unset($resource['data']['attributes']['meta']);
        }

        if (empty($resource['data']['attributes'])) {
            $resource['data']['attributes'] = (object) [];
        }

        if ($this->shouldIncludeLinks()) {
            $resource['data']['links'] = [
                'self' => "{$this->baseUrl}/{$this->getTypePath($resourceKey)}/$id",
            ];
            if (isset($custom_links)) {
                $resource['data']['links'] = array_merge($custom_links, $resource['data']['links']);
            }
        }

        return $resource;
    }

    /**
     * Serialize the included data.
     *
     * @param ResourceInterface $resource
     * @param array $data
     *
     * @return array
     */
    public function includedData(ResourceInterface $resource, array $data)
    {
        list($serializedData, $linkedIds) = $this->pullOutNestedIncludedData($data);

        foreach ($data as $value) {
            foreach ($value as $includeObject) {
                if ($this->isNull($includeObject) || $this->isEmpty($includeObject)) {
                    continue;
                }

                $includeObjects = $this->createIncludeObjects($includeObject);
                list($serializedData, $linkedIds) = $this->serializeIncludedObjectsWithCacheKey(
                    $includeObjects,
                    $linkedIds,
                    $serializedData
                );
            }
        }

        return empty($serializedData) ? [] : ['included' => array_values($serializedData)];
    }

    /**
     * @param array $data
     * @param array $relationships
     *
     * @return array
     */
    protected function fillRelationships($data, $relationships)
    {
        if ($this->isCollection($data)) {
            foreach ($relationships as $key => $relationship) {
                $data = $this->fillRelationshipAsCollection($data, $relationship, $key);
            }
        } else { // Single resource
            foreach ($relationships as $key => $relationship) {
                $data = $this->fillRelationshipAsSingleResource($data, $relationship, $key);
            }
        }

        return $data;
    }

    /**
     * @param array $includedData
     *
     * @return array
     */
    protected function parseRelationships($includedData)
    {
        $relationships = [];

        foreach ($includedData as $key => $inclusion) {
            foreach ($inclusion as $includeKey => $includeObject) {
                $relationships = $this->buildRelationships($includeKey, $relationships, $includeObject, $key);
                if (isset($includedData[0][$includeKey]['meta'])) {
                    $relationships[$includeKey][0]['meta'] = $includedData[0][$includeKey]['meta'];
                }
            }
        }

        return $relationships;
    }

    /**
     * Keep all sideloaded inclusion data on the top level.
     *
     * @param array $data
     *
     * @return array
     */
    protected function pullOutNestedIncludedData(array $data)
    {
        $includedData = [];
        $linkedIds = [];

        foreach ($data as $value) {
            foreach ($value as $includeObject) {
                if (isset($includeObject['included'])) {
                    list($includedData, $linkedIds) = $this->serializeIncludedObjectsWithCacheKey(
                        $includeObject['included'],
                        $linkedIds,
                        $includedData
                    );
                }
            }
        }

        return [$includedData, $linkedIds];
    }

    /**
     * Check if the objects are part of a collection or not
     *
     * @param $includeObject
     *
     * @return array
     */
    private function createIncludeObjects($includeObject)
    {
        if ($this->isCollection($includeObject)) {
            $includeObjects = $includeObject['data'];

            return $includeObjects;
        } else {
            $includeObjects = [$includeObject['data']];

            return $includeObjects;
        }
    }

    /**
     * Loops over the relationships of the provided data and formats it
     *
     * @param $data
     * @param $relationship
     * @param $key
     *
     * @return array
     */
    private function fillRelationshipAsCollection($data, $relationship, $key)
    {
        foreach ($relationship as $index => $relationshipData) {
            $data['data'][$index]['relationships'][$key] = $relationshipData;

            if ($this->shouldIncludeLinks()) {
                $data['data'][$index]['relationships'][$key] = array_merge([
                    'links' => [
                        'self' =>
                            "{$this->baseUrl}/{$this->getTypePath($data['data'][$index]['type'])}/"
                            . "{$data['data'][$index]['id']}/relationships/$key",
                        'related' =>
                            "{$this->baseUrl}/{$this->getTypePath($data['data'][$index]['type'])}/"
                            . "{$data['data'][$index]['id']}/$key",
                    ],
                ], $data['data'][$index]['relationships'][$key]);
            }
        }

        return $data;
    }


    /**
     * @param $data
     * @param $relationship
     * @param $key
     *
     * @return array
     */
    private function fillRelationshipAsSingleResource($data, $relationship, $key)
    {
        $data['data']['relationships'][$key] = $relationship[0];

        if ($this->shouldIncludeLinks()) {
            $data['data']['relationships'][$key] = array_merge([
                'links' => [
                    'self' =>
                        "{$this->baseUrl}/{$this->getTypePath($data['data']['type'])}/"
                        . "{$data['data']['id']}/relationships/$key",
                    'related' =>
                        "{$this->baseUrl}/{$this->getTypePath($data['data']['type'])}/"
                        . "{$data['data']['id']}/$key",
                ],
            ], $data['data']['relationships'][$key]);

            return $data;
        }
        return $data;
    }

    /**
     * @param $includeKey
     * @param $relationships
     * @param $includeObject
     * @param $key
     *
     * @return array
     */
    private function buildRelationships($includeKey, $relationships, $includeObject, $key)
    {
        $relationships = $this->addIncludekeyToRelationsIfNotSet($includeKey, $relationships);

        if ($this->isNull($includeObject)) {
            $relationship = $this->null();
        } elseif ($this->isEmpty($includeObject)) {
            $relationship = [
                'data' => [],
            ];
        } elseif ($this->isCollection($includeObject)) {
            $relationship = ['data' => []];

            $relationship = $this->addIncludedDataToRelationship($includeObject, $relationship);
        } else {
            $relationship = [
                'data' => [
                    'type' => $includeObject['data']['type'],
                    'id' => $includeObject['data']['id'],
                ],
            ];
        }

        $relationships[$includeKey][$key] = $relationship;

        return $relationships;
    }

    /**
     * @param $includeKey
     * @param $relationships
     *
     * @return array
     */
    private function addIncludekeyToRelationsIfNotSet($includeKey, $relationships)
    {
        if (!array_key_exists($includeKey, $relationships)) {
            $relationships[$includeKey] = [];
            return $relationships;
        }

        return $relationships;
    }

    /**
     * @param $includeObject
     * @param $relationship
     *
     * @return array
     */
    private function addIncludedDataToRelationship($includeObject, $relationship)
    {
        foreach ($includeObject['data'] as $object) {
            $relationship['data'][] = [
                'type' => $object['type'],
                'id' => $object['id'],
            ];
        }

        return $relationship;
    }

    /**
     * @param $includeObjects
     * @param $linkedIds
     * @param $serializedData
     *
     * @return array
     */
    private function serializeIncludedObjectsWithCacheKey($includeObjects, $linkedIds, $serializedData)
    {
        foreach ($includeObjects as $object) {
            $includeType = $object['type'];
            $includeId = $object['id'];
            $cacheKey = "$includeType:$includeId";
            if (!array_key_exists($cacheKey, $linkedIds)) {
                $serializedData[$cacheKey] = $object;
                $linkedIds[$cacheKey] = $object;
            } elseif (isset($object['relationships'])) {
                // Preserve nested relationships
                if (isset($serializedData[$cacheKey]['relationships'])) {
                    $serializedData[$cacheKey]['relationships'] = array_merge(
                        $serializedData[$cacheKey]['relationships'],
                        $object['relationships']
                    );
                } else {
                    $serializedData[$cacheKey]['relationships'] = $object['relationships'];
                }
            }
        }
        return [$serializedData, $linkedIds];
    }
}
