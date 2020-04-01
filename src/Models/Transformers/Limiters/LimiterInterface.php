<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models\Transformers\Limiters;

use Illuminate\Http\Request;

interface LimiterInterface
{
    /**
     * LimiterInterface constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request);

    /**
     * Check if limiter has a limit set for a give value
     *
     * @param string $value
     * @return bool
     */
    public function hasLimit(string $value): bool;

    /**
     * Get a limit for a given value
     *
     * @param string $value
     * @return int
     */
    public function getLimit(string $value): int;
}
