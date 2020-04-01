<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Http\Exceptions;

use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ValidationException extends HttpException
{
    /**
     * Non standard meta-information
     * @var array
     */
    protected $meta = [];

    /**
     * @param string $message
     * @param Exception $previous
     */
    public function __construct($message, Exception $previous = null)
    {
        parent::__construct(422, $message, $previous);
    }

    /**
     * Get meta
     * @return array
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Set non standard meta-information
     *
     * @param array $meta
     * @return $this
     */
    public function setMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }
}
