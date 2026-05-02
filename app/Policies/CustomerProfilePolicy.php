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
        return $user->hasAnyRole(['admin', 'staff', 'office', 'secretary']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustomerProfile $customerProfile): bool
    {
        // Admin/staff can view any customer profile
        if ($user->hasAnyRole(['admin', 'staff', 'office', 'secretary'])) {
            return true;
        }

        // Customer can view their own profile
        return $user->id === $customerProfile->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff', 'office', 'secretary']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomerProfile $customerProfile): bool
    {
        // Admin/staff can update any customer profile
        if ($user->hasAnyRole(['admin', 'staff', 'office', 'secretary'])) {
            return true;
        }

        // Customer can update their own profile
        return $user->id === $customerProfile->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomerProfile $customerProfile): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustomerProfile $customerProfile): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustomerProfile $customerProfile): bool
    {
        return $user->hasRole('admin');
    }
}
