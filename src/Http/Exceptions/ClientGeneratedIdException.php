<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Http\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ClientGeneratedIdException extends HttpException
{
    /**
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct(403, $message);
    }
}
