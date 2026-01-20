<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RunAllSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(PrivilegeSeed::class);
        $this->call(RoleAndPrivilegeSeeder::class);
        $this->call(RootAdminSeed::class);
    }
}
