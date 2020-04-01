<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Helpers;

use Proglum\JsonApi\Models\Paginators\InfinityPaginator;
use Illuminate\Pagination\Paginator;

class PaginationHelper
{
    /**
     * @param mixed $items
     * @param string $pageName
     * @return InfinityPaginator
     */
    public static function infinityPaginate($items, string $pageName = 'page'): InfinityPaginator
    {
        return new InfinityPaginator($items, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }
}
