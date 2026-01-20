<?php

namespace App\Http\Controllers\REST\V1\Manage\Privileges;

use App\Http\Libraries\BaseDBRepo;
use App\Models\PrivilegeModel;
use Exception;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = PrivilegeModel::query();

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            $data = $query->get();
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
