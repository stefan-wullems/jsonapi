<?php

namespace Proglum\JsonApi\Providers;

use Proglum\JsonApi\Models\Transformers\Limiters\Limiter;
use Proglum\JsonApi\Models\Transformers\Limiters\LimiterInterface;
use Proglum\JsonApi\Models\Transformers\Sorters\Sorter;
use Proglum\JsonApi\Models\Transformers\Sorters\SorterInterface;
use Illuminate\Support\ServiceProvider;

class JsonApiProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(LimiterInterface::class, Limiter::class);
        $this->app->bind(SorterInterface::class, Sorter::class);
    }
}
