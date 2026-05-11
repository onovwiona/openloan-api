<?php

namespace App\Policies;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerProfilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff', 'secretary', 'marketer']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustomerProfile $profile): bool
    {
        return $user->id === $profile->user_id || $user->hasAnyRole(['admin', 'staff', 'secretary', 'marketer']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff', 'secretary']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomerProfile $profile): bool
    {
        return $user->id === $profile->user_id || $user->hasAnyRole(['admin', 'staff', 'secretary']);
    }

    /**
     * Determine whether the user can verify KYC (admin/staff only)
     */
    public function verifyKyc(User $user, CustomerProfile $profile): bool
    {
        return $user->hasAnyRole(['admin', 'staff']);
    }

    public function rejectKyc(User $user, CustomerProfile $profile): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can change KYC status.
     */
    public function changeKycStatus(User $user, CustomerProfile $profile): bool
    {
        return $user->hasAnyRole(['admin', 'staff']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomerProfile $profile): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustomerProfile $profile): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustomerProfile $profile): bool
    {
        return $user->hasRole('admin');
    }
}

