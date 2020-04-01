<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Http\Controllers;

use Proglum\JsonApi\Endpoint\Contracts\RestEndpoint;
use Proglum\JsonApi\Http\Controllers\Contracts\RestController;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;

class AbstractRestController implements RestController
{
    /**@var RestEndpoint */
    protected $endpointObject;

    /**
     * AbstractRestController constructor.
     *
     * @param RestEndpoint $endpoint
     */
    public function __construct(RestEndpoint $endpoint)
    {
        $this->setEndpoint($endpoint);
    }

    /**
     * Get endpoint
     *
     * @return RestEndpoint
     */
    protected function getEndpoint(): RestEndpoint
    {
        if (!$this->endpointObject) {
            throw new \LogicException('Endpoint is not set');
        }
        return $this->endpointObject;
    }

    /**
     * Set endpoint object
     *
     * @param RestEndpoint $endpoint
     * @return $this
     */
    protected function setEndpoint(RestEndpoint $endpoint)
    {
        $this->endpointObject = $endpoint;
        return $this;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        return $this->getEndpoint()->index($request);
    }

    /**
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function show(Request $request, $id): Response
    {
        return $this->getEndpoint()->show($request, $id);
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        /** @uses \Proglum\JsonApi\Endpoint\Concerns\HandlesResources::store() */
        return $this->getEndpoint()->store($request);
    }

    /**
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function update(Request $request, $id): Response
    {
        /** @uses \Proglum\JsonApi\Endpoint\Concerns\HandlesResources::update() */
        return $this->getEndpoint()->update($request, $id);
    }

    /**
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function destroy(Request $request, $id): Response
    {
        return $this->getEndpoint()->destroy($request, $id);
    }

    /**
     * @param Request $request
     * @param string $column
     * @param $value
     * @return Response
     */
    protected function find(Request $request, string $column, $value = null): Response
    {
        return $this->getEndpoint()->find($request, $column, $value);
    }

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function related(Request $request, $id, $type): Response
    {
        return $this->getEndpoint()->related($request, $id, $type);
    }

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function listRelationship(Request $request, $id, $type): Response
    {
        /** @uses \Proglum\JsonApi\Endpoint\Concerns\HandlesRelationships::listRelationship() */
        return $this->getEndpoint()->listRelationship($request, $id, $type);
    }

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function storeRelationship(Request $request, $id, $type): Response
    {
        /** @uses \Proglum\JsonApi\Endpoint\Concerns\HandlesRelationships::storeRelationship() */
        return $this->getEndpoint()->storeRelationship($request, $id, $type);
    }

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function updateRelationship(Request $request, $id, $type): Response
    {
        /** @uses \Proglum\JsonApi\Endpoint\Concerns\HandlesRelationships::updateRelationship() */
        return $this->getEndpoint()->updateRelationship($request, $id, $type);
    }

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function destroyRelationship(Request $request, $id, $type): Response
    {
        /** @uses \Proglum\JsonApi\Endpoint\Concerns\HandlesRelationships::destroyRelationship() */
        return $this->getEndpoint()->destroyRelationship($request, $id, $type);
    }
}
