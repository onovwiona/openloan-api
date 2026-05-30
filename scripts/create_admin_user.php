<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

$email = 'admin@example.com';
$password = 'password ';
$phone = '+15555550100';
$firstName = 'Admin';
$lastName = 'User';

$role = Role::firstOrCreate(
    ['name' => 'admin'],
    ['guard_name' => 'web', 'description' => 'Super administrator with full access']
);

$user = User::withTrashed()->where('email', $email)->first();

if (! $user) {
    $user = User::create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'email' => $email,
        'password' => Hash::make($password),
        'is_active' => true,
        'phone_verified_at' => now(),
    ]);
    echo "Created admin user {$email}\n";
} else {
    $user->update([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $user->phone ?: $phone,
        'password' => Hash::make($password),
        'is_active' => true,
        'phone_verified_at' => now(),
    ]);
    echo "Updated existing admin user {$email}\n";
    if ($user->trashed()) {
        $user->restore();
        echo "Restored soft-deleted admin user.\n";
    }
}

if (! $user->hasRole('admin')) {
    $user->assignRole($role);
    echo "Assigned admin role.\n";
} else {
    echo "Admin role already assigned.\n";
}

echo "Email: {$email}\nPassword: [password with trailing space]\n";
