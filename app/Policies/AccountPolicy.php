<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'staff', 'secretary', 'marketer']);
    }

    public function view(User $user, Account $account): bool
    {
        return $user->id === $account->customer_id || 
               $user->hasRole(['admin', 'staff', 'secretary', 'marketer']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Account $account): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->hasRole('admin');
    }

    public function viewAccountsForUser(User $authUser, User $user): bool
    {
        return $authUser->id === $user->id || 
               $authUser->hasRole(['admin', 'staff', 'secretary', 'marketer']);
    }

    public function createAccountForUser(User $authUser, User $user): bool
    {
        // Customers can create their own accounts
        return $authUser->id === $user->id && $authUser->hasRole('customer');
    }
}

