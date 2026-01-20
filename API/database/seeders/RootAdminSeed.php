<?php

namespace Database\Seeders;

use App\Models\AccountModel;
use App\Models\RoleModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class RootAdminSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'uuid' => Uuid::uuid4()->toString(),
                'username' => env('ROOT_ADMIN_USERNAME'),
                'password' => env('ROOT_ADMIN_PASSWORD'),
                'role_code' => 'SUPER_ADMIN',
                'deletable' => false,
                'status_active' => true,
                'status_delete' => false,
            ]
        ];

        foreach ($accounts as $data) {
            // Dapatkan ID dari role yang relevan
            $roleId = RoleModel::where('code', $data['role_code'])->value('id');

            unset($data['role_code']);
            $data['role_id'] = $roleId;

            // Buat atau update akun
            AccountModel::updateOrCreate(['uuid' => $data['uuid']], $data);
        }
    }
}
