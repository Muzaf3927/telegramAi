<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\UserRepository;
use App\Repositories\BalanceRepository;
use App\Repositories\TransactionRepository;

class BalanceService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly BalanceRepository $balanceRepository,
        private readonly TransactionRepository $transactionRepository,
    ) {
    }

    public function getBalance(int $userId): float
    {
        $this->userRepository->findOrFail($userId);
        $balance = $this->balanceRepository->getByUserId($userId);
        return (float)($balance?->balance ?? 0);
    }

    public function deposit(int $userId, float $amount, ?string $comment = null): float
    {
        return DB::transaction(function () use ($userId, $amount, $comment) {
            $this->userRepository->findOrFail($userId);

            $balance = $this->balanceRepository->getByUserIdForUpdateOrCreate($userId);
            $balance->balance = bcadd((string)$balance->balance, (string)$amount, 2);
            $this->balanceRepository->save($balance);

            $this->transactionRepository->create([
                'user_id' => $userId,
                'type' => \App\Models\Transaction::TYPE_DEPOSIT,
                'amount' => $amount,
                'balance_after' => $balance->balance,
                'related_user_id' => null,
                'comment' => $comment,
            ]);

            return (float)$balance->balance;
        });
    }

    public function withdraw(int $userId, float $amount, ?string $comment = null): float
    {
        return DB::transaction(function () use ($userId, $amount, $comment) {
            $this->userRepository->findOrFail($userId);

            $balance = $this->balanceRepository->getByUserIdForUpdateOrCreate($userId);

            $newBalance = (float)bcsub((string)$balance->balance, (string)$amount, 2);
            if ($newBalance < 0) {
                throw new \RuntimeException('Insufficient funds');
            }

            $balance->balance = $newBalance;
            $this->balanceRepository->save($balance);

            $this->transactionRepository->create([
                'user_id' => $userId,
                'type' => \App\Models\Transaction::TYPE_WITHDRAW,
                'amount' => $amount,
                'balance_after' => $balance->balance,
                'related_user_id' => null,
                'comment' => $comment,
            ]);

            return (float)$balance->balance;
        });
    }

    public function transfer(int $fromUserId, int $toUserId, float $amount, ?string $comment = null): array
    {
        if ($fromUserId === $toUserId) {
            throw new \InvalidArgumentException('Cannot transfer to the same user');
        }

        return DB::transaction(function () use ($fromUserId, $toUserId, $amount, $comment) {
            $this->userRepository->findOrFail($fromUserId);
            $this->userRepository->findOrFail($toUserId);

            $fromBalance = $this->balanceRepository->getByUserIdForUpdateOrCreate($fromUserId);
            $toBalance = $this->balanceRepository->getByUserIdForUpdateOrCreate($toUserId);

            $newFrom = (float)bcsub((string)$fromBalance->balance, (string)$amount, 2);
            if ($newFrom < 0) {
                throw new \RuntimeException('Insufficient funds');
            }

            $fromBalance->balance = $newFrom;
            $this->balanceRepository->save($fromBalance);

            $toBalance->balance = (float)bcadd((string)$toBalance->balance, (string)$amount, 2);
            $this->balanceRepository->save($toBalance);

            $this->transactionRepository->create([
                'user_id' => $fromUserId,
                'type' => \App\Models\Transaction::TYPE_TRANSFER_OUT,
                'amount' => $amount,
                'balance_after' => $fromBalance->balance,
                'related_user_id' => $toUserId,
                'comment' => $comment,
            ]);

            $this->transactionRepository->create([
                'user_id' => $toUserId,
                'type' => \App\Models\Transaction::TYPE_TRANSFER_IN,
                'amount' => $amount,
                'balance_after' => $toBalance->balance,
                'related_user_id' => $fromUserId,
                'comment' => $comment,
            ]);

            return [
                'from_balance' => (float)$fromBalance->balance,
                'to_balance' => (float)$toBalance->balance,
            ];
        });
    }
}


