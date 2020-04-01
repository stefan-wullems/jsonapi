<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models\Paginators;

use BadMethodCallException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

class InfinityPaginator extends AbstractPaginator implements LengthAwarePaginator
{

    /**
     * The number of items to be shown per page.
     *
     * @var int
     */
    protected $perPage = '-1';

    /**
     * The current page being "viewed".
     *
     * @var int
     */
    protected $currentPage = 1;

    /**
     * Create a new paginator instance.
     *
     * @param  mixed $items
     * @param  array $options (path, query, fragment, pageName)
     */
    public function __construct($items, array $options = [])
    {
        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->path = $this->path != '/' ? rtrim($this->path, '/') : $this->path;
        $this->items = $items instanceof Collection ? $items : Collection::make($items);
    }

    /**
     * Determine the total number of items in the data store.
     *
     * @return int
     */
    public function total()
    {
        return $this->count();
    }

    /**
     * Get the page number of the last available page.
     *
     * @return int
     */
    public function lastPage()
    {
        return 1;
    }

    /**
     * The URL for the next page, or null.
     *
     * @return string|null
     */
    public function nextPageUrl()
    {
        return $this->url(1);
    }

    /**
     * Determine if there is more items in the data store.
     *
     * @return bool
     */
    public function hasMorePages()
    {
        return false;
    }

    /**
     * Render the paginator using a given view.
     *
     * @param  string|null $view
     * @param  array $data
     * @return string
     */
    public function render($view = null, $data = [])
    {
        throw new BadMethodCallException('Not implemented');
    }
}
