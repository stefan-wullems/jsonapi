<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models\Transformers;

use Proglum\JsonApi\Models\Transformers\Limiters\LimiterInterface;
use Proglum\JsonApi\Models\Transformers\Sorters\SorterInterface;
use Proglum\JsonApi\Models\Model;
use App\Log;
use League\Fractal\TransformerAbstract as FractalTransformerAbstract;

/**
 * Class AbstractTransformer
 *
 * @package App\Models\Transformers
 * @method array transform(Model $model)
 */
abstract class AbstractTransformer extends FractalTransformerAbstract
{
    /**
     * Array of API columns (as keys) mapped to database/model fields/properties (as values).
     * When a child class defines this property, callsites using parse to store values in the model should use:
     * ```
     * $parsed = $transformer->parse($attributes);
     * $mapped = $transformer->mapApiToModel($parsed);
     * $model->fill($mapped);
     * ```
     * And callsites using dump to send model values to API endpoints should use:
     * ```
     * $attributes = $transformer->dump($row);
     * $data = $transformer->mapModelToApi($attributes);
     * return response()->json($data);
     * ```
     * Please note that transform() doesn't support the mapModelToApi() as this conflicts with the Fractal internals.
     * If transform() re-uses the dump() logic, implement something like this:
     * ```
     * function transform($model) {
     *   $attributes = $this->dump($model->getAttributes());
     *   $data = $this->mapModelToApi($attributes);
     * }
     * ```
     *
     * @var array|null
     */
    protected $autoColumnMap;

    /**
     * Array of fractal includes to model relationships. This is here to work around an issue where
     * a Eloquent relationship can't have the same name as the model attribute. This is a problem in
     * the case of e.g. dsets, which use the same name for the defined relationship and the model
     * attribute.
     *
     * @var array|null
     */
    protected $includeRelationshipMap;

    /**
     * @var LimiterInterface
     */
    protected $limiter;

    /**
     * @var SorterInterface
     */
    protected $sorter;

    public function __construct(LimiterInterface $limiter, SorterInterface $sorter)
    {
        $this->limiter = $limiter;
        $this->sorter = $sorter;
    }

    /**
     * Get limiter
     *
     * @return LimiterInterface
     */
    protected function getLimiter(): LimiterInterface
    {
        return $this->limiter;
    }

    /**
     * Get sorter
     *
     * @return SorterInterface
     */
    protected function getSorter(): SorterInterface
    {
        return $this->sorter;
    }

    /**
     * Get transformer by name
     *
     * @param string $transformerName
     * @return AbstractTransformer
     */
    protected function getTransformerByName(string $transformerName): AbstractTransformer
    {
        return app()->make($transformerName);
    }

    /**
     * @param array $attributes
     * @return array
     */
    public function mapApiToModel(array $attributes): array
    {
        Log::debug(get_class($this) . ' -  mapApiToModel()');
        if (!isset($this->autoColumnMap)) {
            return $attributes;
        }

        foreach ($this->autoColumnMap as $apiColumn => $dbColumn) {
            if ($dbColumn !== $apiColumn && isset($attributes[$apiColumn])) {
                $attributes[$dbColumn] = $attributes[$apiColumn];
                unset($attributes[$apiColumn]);
            }
        }

        return $attributes;
    }

    /**
     * @param array $attributes
     * @return array
     */
    public function mapModelToApi(array $attributes): array
    {
        if (!isset($this->autoColumnMap)) {
            return $attributes;
        }

        foreach ($this->autoColumnMap as $apiColumn => $dbColumn) {
            if ($dbColumn !== $apiColumn && isset($attributes[$dbColumn])) {
                $attributes[$apiColumn] = $attributes[$dbColumn];
                unset($attributes[$dbColumn]);
            }
        }

        return $attributes;
    }

    public function mapIncludeToRelationship(array $includes): array
    {
        if (!isset($this->includeRelationshipMap)) {
            return $includes;
        }

        foreach ($includes as $key => $include) {
            if (isset($this->includeRelationshipMap[$include])) {
                $includes[$key] = $this->includeRelationshipMap[$include];
            }
        }

        return $includes;
    }

    /**
     * Parse the attributes into database format.
     *
     * These values will be stored in the database.
     *
     * @param array $attributes
     * @return array
     */
    public function parse(array $attributes): array
    {
        Log::debug(get_class($this) . ' - parse()');
        return $attributes;
    }

    /**
     * @param array $row
     * @return array
     */
    public function dump(array $row): array
    {
        return $row;
    }
}
