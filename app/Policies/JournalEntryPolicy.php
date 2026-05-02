<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'auditor']);
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasAnyRole(['admin', 'auditor']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'auditor', 'staff']);
    }

    public function update(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasRole('admin');
    }

    public function reverse(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasRole('admin') && $journalEntry->status === 'posted';
    }
}