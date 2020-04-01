<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models\Transformers\Limiters;

use Illuminate\Http\Request;
use RuntimeException;

class Limiter implements LimiterInterface
{
    /**
     * @var Request
     */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Checks if limiter has a numeric limit set for a give value
     *
     * @param string $value
     * @return bool
     */
    public function hasLimit(string $value): bool
    {
        $related = $this->request->all('related');
        if (!$related || empty($related) || !isset($related['related'])) {
            // No values at all
            return false;
        }

        // Both simple queries and two step queries should work:
        // Simple: related[steplogs][limit]
        // Two-step: related[steps.steplogs][limit]
        foreach ($related['related'] as $limitName => $limitInfo) {
            // Check if a limit - ends with [limit]
            if (!isset($limitInfo['limit']) || !is_numeric($limitInfo['limit'])) {
                continue;
            }

            $limitNameArray = explode('.', $limitName);
            $lastPart = end($limitNameArray);
            if ($lastPart === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a limit for a given value
     *
     * @param string $value
     * @return int
     */
    public function getLimit(string $value): int
    {
        if (!$this->hasLimit($value)) {
            throw new RuntimeException('Limit not set');
        }

        $related = $this->request->all('related');
        foreach ($related['related'] as $limitName => $limitData) {
            // Check if a limit - ends with [limit]
            if (!isset($limitData['limit'])) {
                continue;
            }

            $limitNameArray = explode('.', $limitName);
            $lastPart = end($limitNameArray);
            if ($lastPart === $value) {
                return (int) $limitData['limit'];
            }
        }
    }
}
