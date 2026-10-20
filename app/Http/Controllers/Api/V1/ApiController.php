<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON envelope for /api/v1 (spec §8.1):
 *   { "data": …, "meta": { pagination }, "request_id": "…" }
 */
abstract class ApiController extends Controller
{
    protected function respond($data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(array_filter([
            'data' => $data instanceof \Illuminate\Http\Resources\Json\JsonResource ? $data->resolve(request()) : $data,
            'meta' => $meta ?: null,
            'request_id' => request()->attributes->get('request_id'),
        ], fn ($v) => $v !== null), $status);
    }

    protected function created($data): JsonResponse
    {
        return $this->respond($data, 201);
    }

    /** Paginate with an allow-listed sort and a bounded page size. */
    protected function paginate(Builder $query, Request $request, string $resource, array $sortable = ['id', 'created_at'], string $default = '-created_at'): JsonResponse
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string'],
        ]);

        $sort = $request->query('sort', $default);
        $column = ltrim($sort, '-');
        if (! in_array($column, $sortable, true)) {
            abort(400, 'Unsupported sort field. Allowed: '.implode(', ', $sortable));
        }
        $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('id', 'desc');

        /** @var LengthAwarePaginator $page */
        $page = $query->paginate($request->integer('limit', 25))->withQueryString();

        return $this->respond($resource::collection($page->getCollection())->resolve($request), 200, [
            'page' => $page->currentPage(),
            'limit' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    protected function requireSection(Request $request, string ...$sections): void
    {
        foreach ($sections as $s) {
            if ($request->user()->canSee($s)) {
                return;
            }
        }
        abort(403, 'Your role does not have access to this resource.');
    }
}
