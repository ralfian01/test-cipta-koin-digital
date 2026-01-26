<?php

namespace App\Http\Controllers\REST\V1\Account\Register;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountModel;
use App\Models\RoleModel;
use Ramsey\Uuid\Uuid;
use Exception;

/**
 * 
 */
class DBRepo extends BaseDBRepo
{
    /*
     * ---------------------------------------------
     * TOOLS
     * ---------------------------------------------
     */


    /*
     * ---------------------------------------------
     * DATABASE TRANSACTION
     * ---------------------------------------------
     */

    /** 
     * Function to get data from database
     * @return array|null|object
     */
    public function insertAccount()
    {
        ## Formatting additional data which not payload
        // Get role id from role code
        $roleId = RoleModel::where('code', "USER")->value('id');

        ## Formatting payload
        // Code here...

        try {

            $payload = [
                'uuid' => Uuid::uuid4()->toString(),
                'username' => $this->payload['username'],
                'password' => $this->payload['password'],
                'role_id' => $roleId,
                'deletable' => true,
                'status_active' => true,
                'status_delete' => false,
            ];

            // Buat atau update akun
            $created = AccountModel::create($payload);

            return (object) [
                'status' => $created
            ];
        } catch (Exception $e) {

            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
