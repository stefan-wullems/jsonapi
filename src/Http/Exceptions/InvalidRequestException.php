<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Http\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidRequestException extends HttpException
{
    /**
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct(400, $message);
    }
}
