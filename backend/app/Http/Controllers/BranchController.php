<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterBranchRequest;
use App\Http\Resources\BranchResource;
use App\Http\Responses\FilterResponse;
use App\Repositories\BranchRepositoryInterface;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    public function __construct(private readonly BranchRepositoryInterface $Branchs) {}

    /** Filtrado avanzado de sucursales. */
    public function filter(FilterBranchRequest $request): JsonResponse
    {
        $result = $this->Branchs->filter($request->filters(), $request->orderBy(), $request->pagination());

        return response()->json(FilterResponse::from($result, BranchResource::class)->toResponse());
    }
}
