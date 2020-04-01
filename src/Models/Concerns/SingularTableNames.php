<?php

namespace Proglum\JsonApi\Models\Concerns;

use Illuminate\Support\Str;

trait SingularTableNames
{
    /**
     * Override the method fetching the table name, because we use singular
     * tables.
     *
     * @return string
     */
    public function getTable()
    {
        if (!isset($this->table)) {
            return static::getTableFromClassName();
        }

        return $this->table;
    }

    /**
     * @return string
     */
    public static function getTableFromClassName(): string
    {
        return str_replace(
            '\\',
            '',
            Str::snake(class_basename(static::class))
        );
    }
}
