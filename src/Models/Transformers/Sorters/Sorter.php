<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Models\Transformers\Sorters;

use Illuminate\Http\Request;
use RuntimeException;

class Sorter implements SorterInterface
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * Sorter constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Check if the given value needs to be sorted
     *
     * @param string $value
     * @return bool
     */
    public function hasSort(string $value): bool
    {
        $related = $this->request->all('related');
        if (!$related || empty($related) || !isset($related['related'])) {
            // No values at all
            return false;
        }

        // Both simple queries and two step queries should work:
        // Simple: related[steplogs][sort]
        // Two-step: related[steps.steplogs][sort]
        foreach ($related['related'] as $sortName => $sortInfo) {
            // Check if a sort - ends with [sort]
            if (!isset($sortInfo['sort'])) {
                continue;
            }

            // See if the value is at the end of the name string
            $sortNameArray = explode('.', $sortName);
            $lastPart = end($sortNameArray);
            if ($lastPart === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get "order by" column name
     *
     * @param string $value
     * @return string
     */
    public function getColumn(string $value): string
    {
        if (!$this->hasSort($value)) {
            throw new RuntimeException('Sort not set');
        }

        $orderBy = $this->getOrderByForValue($value);
        // The value might have a dot in it to distinguish the column name from the direction
        // (eg. related[steplogs][sort] = start_date.desc). We only want what's BEFORE the dot.
        if (substr($orderBy, 0, 1) == '-') {
            return substr($orderBy, 1);
        }
        return $orderBy;
    }

    /**
     * Get 'order by' field for given value
     *
     * @param string $value
     * @return string
     */
    protected function getOrderByForValue(string $value): string
    {
        $related = $this->request->all('related');
        foreach ($related['related'] as $sortName => $sortInfo) {
            // Check if a sort - ends with [sort]
            if (!isset($sortInfo['sort'])) {
                continue;
            }
            
            $sortNameArray = explode('.', $sortName);
            $lastPart = end($sortNameArray);
            if ($lastPart === $value) {
                return $sortInfo['sort'];
            }
        }
    }

    /**
     * Get "order by" direction
     *
     * @param string $value Column name
     * @return string ASC|DESC
     */
    public function getDirection(string $value): string
    {
        $orderBy = $this->getOrderByForValue($value);

        // If the value has a leading minus sign, the order is desc. Otherwise asc.
        // (eg. related[steplogs][sort] = -start_date)
        if (substr($orderBy, 0, 1) == '-') {
            return 'DESC';
        }
        return 'ASC';
    }
}
