<?php

namespace Database\Seeders;

use App\Models\Privilege; // Sesuaikan path model
use App\Models\PrivilegeModel;
use App\Models\Role;      // Sesuaikan path model
use App\Models\RoleModel;
use Illuminate\Database\Seeder;

class RoleAndPrivilegeSeeder extends Seeder
{
    public function run(): void
    {
        // Definisikan peran dan hak aksesnya
        $rolesAndPrivileges = [
            'SUPER_ADMIN' => [
                'name' => 'Super Administrator',
                'privileges' => [
                    'GLOBAL_ACCESS',
                    'PRIVILEGE_MANAGE_VIEW',
                    'ROLE_MANAGE_VIEW',
                    'ROLE_MANAGE_INSERT',
                    'ROLE_MANAGE_UPDATE',
                    'ROLE_MANAGE_DELETE',
                    'ACCOUNT_MANAGE_VIEW',
                    'ACCOUNT_MANAGE_INSERT',
                    'ACCOUNT_MANAGE_UPDATE',
                    'ACCOUNT_MANAGE_DELETE',
                    'TODO_VIEW',
                    'TODO_INSERT',
                    'TODO_UPDATE',
                    'TODO_DELETE',
                ]
            ],
            'USER' => [
                'name' => 'User',
                'privileges' => [
                    'TODO_VIEW',
                    'TODO_INSERT',
                    'TODO_UPDATE',
                    'TODO_DELETE',
                ]
            ],
        ];

        foreach ($rolesAndPrivileges as $roleCode => $roleData) {
            // Buat atau update peran
            $role = RoleModel::updateOrCreate(['code' => $roleCode], ['name' => $roleData['name']]);

            // Dapatkan ID dari privilege yang relevan
            $privilegeIds = PrivilegeModel::whereIn('code', $roleData['privileges'])->pluck('id');

            // Sinkronkan relasi di tabel pivot
            $role->rolePrivilege()->sync($privilegeIds);
        }
    }
}
