<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BalanceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\DepositRequest;
use App\Http\Requests\WithdrawRequest;
use App\Http\Requests\TransferRequest;

class BalanceController extends Controller
{
    public function __construct(private readonly BalanceService $balanceService)
    {
    }

    public function deposit(DepositRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $balance = $this->balanceService->deposit($data['user_id'], (float)$data['amount'], $data['comment'] ?? null);
            return response()->json([
                'user_id' => (int)$data['user_id'],
                'balance' => $balance,
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'User not found'], 404);
        }
    }

    public function withdraw(WithdrawRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $balance = $this->balanceService->withdraw($data['user_id'], (float)$data['amount'], $data['comment'] ?? null);
            return response()->json([
                'user_id' => (int)$data['user_id'],
                'balance' => $balance,
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'User not found'], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Insufficient funds'], 409);
        }
    }

    public function transfer(TransferRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->balanceService->transfer(
                (int)$data['from_user_id'],
                (int)$data['to_user_id'],
                (float)$data['amount'],
                $data['comment'] ?? null
            );

            return response()->json([
                'from_user_id' => (int)$data['from_user_id'],
                'to_user_id' => (int)$data['to_user_id'],
                'amount' => (float)$data['amount'],
                'from_balance' => $result['from_balance'],
                'to_balance' => $result['to_balance'],
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'User not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Insufficient funds'], 409);
        }
    }

    public function balance(int $userId): JsonResponse
    {
        try {
            $balance = $this->balanceService->getBalance($userId);
            return response()->json([
                'user_id' => $userId,
                'balance' => $balance,
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'User not found'], 404);
        }
    }
}


