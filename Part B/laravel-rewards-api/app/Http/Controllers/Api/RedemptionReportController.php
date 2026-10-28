<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RedemptionReportRequest;
use App\Models\Redemption;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class RedemptionReportController extends Controller
{
    public function index(RedemptionReportRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = Redemption::query();

        if (! empty($filters['from'])) {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'])->startOfDay();
            $query->where('redeemed_at', '>=', $start);
        }

        if (! empty($filters['to'])) {
            $endExclusive = CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'])
                ->addDay()->startOfDay();
            $query->where('redeemed_at', '<', $endExclusive);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        // These aggregates cover the entire filtered set, not merely one page.
        $summary = (clone $query)
            ->selectRaw('COUNT(*) AS total_count, COALESCE(SUM(points_spent), 0) AS total_points')
            ->first();

        $perPage = (int) ($filters['per_page'] ?? 50);
        $page = (clone $query)
            ->orderByDesc('redeemed_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows = collect($page->items())->map(fn (Redemption $r): array => [
            'id' => $r->id,
            'user_id' => $r->user_id,
            'product_id' => $r->product_id,
            'category_id' => $r->category_id,
            'points_spent' => $r->points_spent,
            'redeemed_at' => $r->redeemed_at->toIso8601String(),
        ])->all();

        return response()->json([
            'data' => $rows,
            'total_points' => (int) $summary->total_points,
            'count' => (int) $summary->total_count,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }
}
