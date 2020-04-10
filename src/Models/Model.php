<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models;

use Proglum\JsonApi\Models\Concerns\ChecksRelations;
use Proglum\JsonApi\Models\Concerns\SingularTableNames;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @mixin \Eloquent
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
abstract class Model extends EloquentModel
{
    use SingularTableNames;
    use ChecksRelations;

    /** @var string|callable Used to identify model with friendly name */
    public $titleField;

    protected $keyType = 'string';

    /** @var string Used to map to audit category */
    public $auditCategory;

    /**
     * Customise the format of the "created_at" and "updated_at"
     * "U" - timestamp
     * @var string
     */
    protected $dateFormat = "U";

    /** @var string|null JSON API resource type, null for default (table name) */
    public static $resourceName;

    /**
     * @param string $attribute
     * @return bool
     */
    public function isFilterable(string $attribute): bool
    {
        if (in_array($attribute, $this->getVisible())) {
            return true;
        }

        // Relation - must contain a dot (example: usergroup.permissions)
        if (strpos($attribute, '.') !== false) {
            $attribute = current(explode('.', $attribute));
            if (self::isRelation($attribute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $attribute
     * @return bool
     */
    public function isSortable(string $attribute): bool
    {
        return $this->isFilterable($attribute);
    }

    /**
     * Get the class name for polymorphic relations.
     *
     * @return string
     */
    public function getMorphClass(): string
    {
        $morphMap = Relation::morphMap();

        if (! empty($morphMap) && in_array(static::class, $morphMap)) {
            return (string) array_search(static::class, $morphMap, true);
        }

        return $this->getTable();
    }

    /**
     * @return string
     */
    public static function resourceName(): string
    {
        return static::$resourceName ?? static::getTableFromClassName();
    }
}
