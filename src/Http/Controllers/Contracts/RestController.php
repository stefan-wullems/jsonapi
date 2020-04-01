<?php

declare(strict_types=1);

namespace Proglum\JsonApi\Http\Controllers\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface RestController
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response;

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
     * @param $relationshipType
     * @return Response
     */
    public function related(Request $request, $id, $relationshipType): Response;
}
