<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RedemptionDeniedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRedemptionRequest;
use App\Services\RedemptionService;
use Illuminate\Http\JsonResponse;

class RedemptionController extends Controller
{
    public function store(StoreRedemptionRequest $request, RedemptionService $service): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $service->redeem((int) $data['user_id'], (int) $data['product_id']);
        } catch (RedemptionDeniedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], 422);
        }

        $redemption = $result['redemption'];

        return response()->json([
            'message' => 'Redemption successful.',
            'data' => [
                'redemption_id' => $redemption->id,
                'reference' => $redemption->reference,
                'user_id' => $redemption->user_id,
                'product_id' => $redemption->product_id,
                'category_id' => $redemption->category_id,
                'points_spent' => $redemption->points_spent,
                'remaining_balance' => $result['remaining_balance'],
                'redeemed_at' => $redemption->redeemed_at->toIso8601String(),
            ],
        ], 201);
    }
}
