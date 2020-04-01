<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Http;

use Illuminate\Http\JsonResponse;

class JsonApiResponse extends JsonResponse
{
    /**
     * JsonApiResponse constructor.
     *
     * @param array $data
     * @param int $status
     * @param array $headers
     * @param int $options
     */
    public function __construct(array $data = [], int $status = 200, array $headers = [], int $options = 0)
    {
        parent::__construct($data, $status, $headers, $options);

        $this->headers->set('Content-Type', 'application/vnd.api+json');
    }
}
