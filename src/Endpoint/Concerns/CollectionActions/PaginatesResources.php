<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Concerns\CollectionActions;

use Proglum\JsonApi\Helpers\PaginationHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

trait PaginatesResources
{
    /**
     * Default page size.
     *
     * @var int
     */
    protected $pageSizeDefault = 10;

    /**
     * Query string parameter to read/write page number to.
     *
     * @var string
     */
    protected $pageNumberParameter = 'page.offset';

    /**
     * Query string parameter to read/write page size to.
     *
     * @var string
     */
    protected $pageSizeParameter = 'page.limit';

    /**
     * @param Builder $query
     * @return LengthAwarePaginator
     */
    protected function paginate(Builder $query): LengthAwarePaginator
    {
        /** @var Request $request */
        $request = app('request');

        if ($this->paginatesToInfinity()) {
            $paginator = PaginationHelper::infinityPaginate($query->get(), $this->pageNumberParameter);
        } else {
            $size = $this->getPageSizeParameter();
            $paginator = $query->paginate($size, ['*'], $this->pageNumberParameter);
        }

        // The setPageName call is needed unless https://github.com/laravel/framework/issues/19167 is solved
        $paginator->setPageName(preg_replace("/(.*)\.(.*)/", "$1[$2]", $this->pageNumberParameter));

        $paginator->appends(Arr::except($request->input(), $this->pageNumberParameter));

        return $paginator;
    }

    /**
     * @return bool
     */
    protected function paginatesToInfinity(): bool
    {
        return $this->getPageSizeParameter() === "-1";
    }

    /**
     * @return string
     */
    private function getPageSizeParameter(): string
    {
        /** @var Request $request */
        $request = app('request');

        return (string) $request->input($this->pageSizeParameter, $this->pageSizeDefault);
    }
}
