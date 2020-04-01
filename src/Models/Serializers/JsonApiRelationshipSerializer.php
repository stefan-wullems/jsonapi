<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models\Serializers;

class JsonApiRelationshipSerializer extends JsonApiSerializer
{
    /**
     * Serialize an item.
     *
     * @param string $resourceKey
     * @param array $data
     * @return array
     */
    public function item($resourceKey, array $data)
    {
        $item = parent::item($resourceKey, $data);

        unset($item['data']['attributes']);
        unset($item['data']['links']);

        return $item;
    }
}
