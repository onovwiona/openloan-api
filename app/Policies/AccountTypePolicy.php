<?php

namespace App\Policies;

use App\Models\AccountType;
use App\Models\User;

class AccountTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Publicly readable
    }

    public function view(User $user, AccountType $accountType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, AccountType $accountType): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, AccountType $accountType): bool
    {
        return $user->hasRole('admin');
    }
}