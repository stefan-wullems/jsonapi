<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Endpoint\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface RestEndpoint extends Endpoint
{
    /**
     * @param Request $request
     * @param Builder|null $query
     * @return Response
     */
    public function index(Request $request, ?Builder $query = null): Response;

    /**
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function show(Request $request, $id): Response;

    /**
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response;

    /**
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function update(Request $request, $id): Response;

    /**
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function destroy(Request $request, $id): Response;

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function related(Request $request, $id, $type): Response;

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function listRelationship(Request $request, $id, $type): Response;

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function storeRelationship(Request $request, $id, $type): Response;

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function updateRelationship(Request $request, $id, $type): Response;

    /**
     * @param Request $request
     * @param $id
     * @param $type
     * @return Response
     */
    public function destroyRelationship(Request $request, $id, $type): Response;
}
