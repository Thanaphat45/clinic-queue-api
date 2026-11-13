<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Demo data only (fictional). Password for both accounts: password */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create(['name' => 'Admin (Demo)', 'email' => 'admin@example.com', 'password' => 'password', 'role' => Role::Admin]);
        User::create(['name' => 'Staff (Demo)', 'email' => 'staff@example.com', 'password' => 'password', 'role' => Role::Staff]);

        foreach ([
            ['code' => 'A', 'name' => 'ตรวจโรคทั่วไป', 'avg_minutes' => 12],
            ['code' => 'B', 'name' => 'ทันตกรรม', 'avg_minutes' => 25],
            ['code' => 'C', 'name' => 'รับยา', 'avg_minutes' => 4],
        ] as $s) {
            Service::create($s);
        }
    }
}
