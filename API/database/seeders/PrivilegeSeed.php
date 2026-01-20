<?php

namespace Database\Seeders;

use App\Models\PrivilegeModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrivilegeSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $privilegeCodes = [
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
        ];

        $privilegeDesc = [
            "Global Access",
            "VIEW Privilege",
            "Role Manage View",
            "Role Manage Insert",
            "Role Manage Update",
            "Role Manage Delete",
            "Account Manage View",
            "Account Manage Insert",
            "Account Manage Update",
            "Account Manage Delete",
            "Todo View",
            "Todo Insert",
            "Todo Update",
            "Todo Delete",
        ];

        foreach ($privilegeCodes as $key => $privilege) {
            PrivilegeModel::updateOrCreate(
                ['code' => $privilege],
                [
                    'description' => array_key_exists($key, $privilegeDesc) ? $privilegeDesc[$key] : null
                ]
            );
        }
    }
}
