<?php

namespace App\Policies;

use App\Models\LedgerAccount;
use App\Models\User;

class LedgerAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'auditor', 'staff']);
    }

    public function view(User $user, LedgerAccount $ledgerAccount): bool
    {
        return $user->hasAnyRole(['admin', 'auditor', 'staff']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, LedgerAccount $ledgerAccount): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, LedgerAccount $ledgerAccount): bool
    {
        return $user->hasRole('admin');
    }
}