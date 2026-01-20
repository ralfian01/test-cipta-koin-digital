<?php

namespace App\Http\Controllers\REST\V1\My;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountModel;
use App\Models\ApplicantModel;
use App\Models\IndonesianRegion\CityModel;
use App\Models\IndonesianRegion\DistrictModel;
use App\Models\IndonesianRegion\ProvinceModel;
use App\Models\IndonesianRegion\VillageModel;
use App\Models\PrivilegeModel;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Psy\Readline\Hoa\Console;

/**
 * 
 */
class DBRepo extends BaseDBRepo
{
    // public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    // {
    //     parent::__construct($payload, $file, $auth);
    // }

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
    public function getUserData()
    {
        ## Formatting additional data which not payload
        // Code here...

        ## Formatting payload
        // Code here...

        try {

            $data =
                AccountModel::with([
                    'profile' => function ($query) {
                        $query->select([
                            "id",
                            "id",
                            "tpr_id",
                            "tpr_name as name",
                            "tpr_nip as nip",
                            "tpr_digitalSignature as digital_signature",
                            "tpr_digitalInitials as digital_initials",
                        ]);
                    },
                    'profile.position' => function ($query) {
                        $query->select([
                            "id",
                            "name as name",
                            "statusActive as status_active",
                            "parentId as parent_id"
                        ]);
                    }
                ])
                ->find($this->auth['account_id']);
            $data->makeHidden(['id', 'tr_id', 'deletable', 'statusActive', 'statusDelete', 'uuid', 'username']);
            $data->username = $data->username;
            $data->uuid = $data->uuid;

            // AccountModel::with([
            //     'accountPrivilege',
            //     'accountRole.rolePrivilege'
            // ])
            // // ->select(['id', 'tr_id'])
            // ->where('id', $this->auth['account_id'])
            // ->get()
            // ->map(function ($acc) {

            //     $acc->makeHidden(['accountPrivilege', 'accountRole', 'id', 'tr_id']);

            //     if (isset($acc->accountPrivilege)) {
            //         $acc->privileges = $acc->accountPrivilege->map(function ($prv) {
            //             return $prv->code;
            //         })->toArray();
            //     }

            //     if (isset($acc->accountRole->rolePrivilege)) {
            //         $acc->privileges = array_unique(
            //             $acc->accountRole->rolePrivilege->map(function ($prv) {
            //                 return $prv->code;
            //             })->toArray()
            //         );
            //     }

            //     return $acc->privileges;
            // });

            return (object) [
                'status' => $data != null,
                'data' => $data->toArray()
            ];
        } catch (Exception $e) {

            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /** 
     * Function to get data from database
     * @return array|null|object
     */
    public function getPrivileges()
    {
        ## Formatting additional data which not payload
        // Code here...

        ## Formatting payload
        // Code here...

        try {

            $data =
                AccountModel::with(['accountPrivilege', 'roles.rolePrivilege'])
                ->where('id', $this->auth['account_id'])
                ->get()
                ->map(function ($acc) {

                    if (isset($acc->accountPrivilege)) {
                        $acc->privileges = $acc->accountPrivilege->map(function ($prv) {
                            return $prv->code;
                        })->toArray();
                    }

                    if (isset($acc->roles)) {
                        $acc->privileges = $acc->roles->map(function ($rov) {
                            return $rov->rolePrivilege->map(function ($prv) {
                                return $prv->code;
                            })->toArray();
                        })->first();
                    }

                    $acc->privileges = array_unique($acc->privileges);
                    return $acc->privileges;
                });

            // var_dump($data);

            return (object) [
                'status' => !$data->isEmpty(),
                'data' => $data->isEmpty() ? null : $data->first()
            ];
        } catch (Exception $e) {

            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
