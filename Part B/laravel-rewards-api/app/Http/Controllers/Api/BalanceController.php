<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PointsBalanceService;
use Illuminate\Http\JsonResponse;

class BalanceController extends Controller
{
    public function show(int $id, PointsBalanceService $balances): JsonResponse
    {
        $user = User::query()->find($id);

        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return response()->json([
            'user_id' => $user->id,
            'balance' => $balances->balanceFor($user->id),
        ]);
    }
}
