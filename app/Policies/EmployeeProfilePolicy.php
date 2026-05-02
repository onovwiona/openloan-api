<?php

namespace App\Policies;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmployeeProfilePolicy
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
    public function view(User $user, EmployeeProfile $employeeProfile): bool
    {
        // Admin/staff can view any employee profile
        if ($user->hasAnyRole(['admin', 'staff', 'office', 'secretary'])) {
            return true;
        }

        // Employee can view their own profile
        return $user->id === $employeeProfile->user_id;
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
    public function update(User $user, EmployeeProfile $employeeProfile): bool
    {
        // Admin/staff can update any employee profile
        if ($user->hasAnyRole(['admin', 'staff', 'office', 'secretary'])) {
            return true;
        }

        // Employee can update their own profile
        return $user->id === $employeeProfile->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmployeeProfile $employeeProfile): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmployeeProfile $employeeProfile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmployeeProfile $employeeProfile): bool
    {
        return false;
    }
}
