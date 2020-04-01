<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models\Transformers;

use Proglum\JsonApi\Models\Model;

class RelationTransformer extends AbstractTransformer
{
    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected $availableIncludes = [];

    /**
     * Turn this item object into a generic array
     *
     * @param Model $model
     * @return array
     */
    public function transform($model)
    {
        return [
          'id' => $model->getKey(),
        ];
    }
}
