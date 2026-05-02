<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'guard_name' => 'web',
                'description' => 'Super administrator with full access',
            ],
            [
                'name' => 'staff',
                'guard_name' => 'web',
                'description' => 'Staff member with customer management access',
            ],
            [
                'name' => 'office',
                'guard_name' => 'web',
                'description' => 'Office staff with administrative duties',
            ],
            [
                'name' => 'secretary',
                'guard_name' => 'web',
                'description' => 'Secretary with limited administrative access',
            ],
            [
                'name' => 'marketer',
                'guard_name' => 'web',
                'description' => 'Marketing staff with referral management access',
            ],
            [
                'name' => 'auditor',
                'guard_name' => 'web',
                'description' => 'Auditor with finance and compliance access',
            ],
            [
                'name' => 'customer',
                'guard_name' => 'web',
                'description' => 'Customer with self-service access',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}
