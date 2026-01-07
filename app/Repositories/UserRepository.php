<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserRepository
{
    public function findOrFail(int $userId): User
    {
        $user = User::find($userId);
        if (!$user) {
            throw new ModelNotFoundException('User not found');
        }
        return $user;
    }
}


