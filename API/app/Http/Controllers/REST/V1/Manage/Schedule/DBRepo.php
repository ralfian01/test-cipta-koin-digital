<?php

namespace App\Http\Controllers\REST\V1\Manage\Schedule;

use App\Http\Libraries\BaseDBRepo;
use App\Models\EmployeeModel;
use App\Models\MachineModel;
use App\Models\ProductModel;
use App\Models\ScheduleEmployeeModel;
use App\Models\ScheduleModel;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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

    /**
     * Function to check schedule id
     * @return bool
     */
    public static function checkScheduleId($id)
    {
        return ScheduleModel::find($id) != null;
    }

    /**
     * Function to check machine id
     * @return bool
     */
    public static function checkMachineId($id)
    {
        return MachineModel::find($id) != null;
    }

    /**
     * Function to check product id
     * @return bool
     */
    public static function checkProductId($id)
    {
        return ProductModel::find($id) != null;
    }

    /**
     * Function to check employee ids
     * @return bool
     */
    public static function checkEmployeeIds(array $ids, &$invalidIds)
    {
        $employees = EmployeeModel::whereIn('te_id', $ids)->get(['te_id'])->toArray();

        $foundIds = array_column($employees, 'te_id');
        $invalidIds = array_diff($ids, $foundIds);

        return empty($invalidIds);
    }


    /*
     * ---------------------------------------------
     * DATABASE TRANSACTION
     * ---------------------------------------------
     */

    /**
     * Function to get data from database
     * @return array|null|object
     */
    public function getData()
    {
        ## Formatting additional data which not payload
        // Code here...

        ## Formatting payload
        // ## Formatting payload production_date
        if (isset($this->payload['production_date'])) {
            $productionDateRange = explode(',', $this->payload['production_date']);
        }

        // ## Formatting payload machine_code
        if (isset($this->payload['machine_code'])) {
            $machines = MachineModel::whereIn('tm_code', explode(',', $this->payload['machine_code']))->get('tm_id')->toArray();
            $machineIds = array_column($machines, 'tm_id');
        }

        try {

            $data =
                ScheduleModel::with([
                    'machine' => function ($query) {
                        return $query->select(
                            'tm_id',
                            'tm_id as id',
                            'tm_code as code',
                            'tm_name as name',
                        );
                    },
                    'product' => function ($query) {
                        return $query->select(
                            'tpr_id',
                            'tpr_id as id',
                            'tpr_name as name',
                            'tpr_weight as weight',
                            'tpr_expired as expired',
                            'tpr_imagePath as image',
                        );
                    },
                    'scheduleEmployee' => function ($query) {
                        return $query->select(
                            'schedule_employee.tsc_id',
                            'employee.te_id',
                            'te_name as name',
                            'te_statusActive as status_active'
                        );
                    }
                ])
                ->select([
                    'tsc_id',
                    'tm_id',
                    'tpr_id',
                    'tsc_id as id',
                    'tsc_shiftCode as shift_code',
                    'tsc_productionDate as production_date',
                    'tsc_expiredDate as expired_date',
                ]);

            // Filter by id
            if (isset($this->payload['id'])) {
                $data = $data->where('tsc_id', $this->payload['id']);
            } else {
                // ## Filter by production date (range or specific date)
                if (isset($this->payload['production_date'])) {
                    if (count($productionDateRange) >= 2) {
                        $data = $data->whereBetween('tsc_productionDate', $productionDateRange);
                    } else {
                        $data = $data->where('tsc_productionDate', $productionDateRange[0]);
                    }
                }

                // ## Filter by machine codes
                if (isset($this->payload['machine_code'])) {
                    $data = $data->whereIn('tm_id', $machineIds ?? []);
                }

                // ## Filter by product ids
                if (isset($this->payload['product_id'])) {
                    $data = $data->whereIn('tpr_id', explode(',', $this->payload['product_id']));
                }

                // ## Filter by expired date
                if (isset($this->payload['expired_date'])) {
                    $data = $data->where('tsc_expiredDate', $this->payload['expired_date']);
                }

                // ## Filter by shift code
                if (isset($this->payload['shift_code'])) {
                    $data = $data->where('tsc_shiftCode', $this->payload['shift_code']);
                }
            }

            $data = $data->get();

            return (object) [
                'status' => !$data->isEmpty(),
                'data' => $data->isEmpty()
                    ? null
                    : (isset($this->payload['id'])
                        ? $data->toArray()[0]
                        : $data->toArray())
            ];
        } catch (Exception $e) {

            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Function to insert data from database
     * @return object|bool
     */
    public function insertData()
    {
        ## Formatting additional data which not payload
        // Code here...

        ## Formatting payload
        // Code here...

        try {

            return DB::transaction(function () {

                // If id found and Delete keys that have a null value
                $dbPayload = Arr::whereNotNull([
                    'tsc_shiftCode' => $this->payload['shift_code'] ?? null,
                    'tsc_productionDate' => $this->payload['production_date'] ?? null,
                    'tsc_expiredDate' => $this->payload['expired_date'] ?? null,
                    'tpr_id' => $this->payload['product_id'] ?? null,
                    'tm_id' => $this->payload['machine_id'] ?? null,
                ]);

                ## Insert schedule
                $insertData = ScheduleModel::create($dbPayload);

                if (!$insertData) {
                    $tableName = ScheduleModel::tableName();
                    throw new Exception("Failed when insert data into table \"{$tableName}\"");
                }


                ## Employee schedule pivot
                $employeesPayload = [];
                foreach ($this->payload['employee_ids'] as $emp) {
                    $employeesPayload[] = [
                        'tsc_id' => $insertData->tsc_id,
                        'te_id' => $emp
                    ];
                }

                ## Insert employee schedule
                $employeeSchedule = ScheduleEmployeeModel::insert($employeesPayload);

                if (!$employeeSchedule) {
                    $tableName = ScheduleEmployeeModel::tableName();
                    throw new Exception("Failed when insert data into table \"{$tableName}\"");
                }


                // Return transaction status
                return (object) [
                    'status' => true,
                ];
            });
        } catch (Exception $e) {

            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Function to update data from database
     * @return object|bool
     */
    public function updateData()
    {
        ## Formatting additional data which not payload
        // Code here...

        ## Formatting payload
        // Code here...

        try {

            return DB::transaction(function () {

                // If id found and Delete keys that have a null value
                $dbPayload = Arr::whereNotNull([
                    'tsc_shiftCode' => $this->payload['shift_code'] ?? null,
                    'tsc_productionDate' => $this->payload['production_date'] ?? null,
                    'tsc_expiredDate' => $this->payload['expired_date'] ?? null,
                    'tpr_id' => $this->payload['product_id'] ?? null,
                    'tm_id' => $this->payload['machine_id'] ?? null,
                ]);

                ## Update data
                $updateData = ScheduleModel::find($this->payload['id'])->update($dbPayload);

                if (!$updateData) {
                    $tableName = ScheduleModel::tableName();
                    throw new Exception("Failed when update data into table \"{$tableName}\"");
                }

                // ## Employee schedule pivot
                // $employeesPayload = [];
                // foreach ($this->payload['employee_ids'] as $emp) {
                //     $employeesPayload[] = [
                //         'tsc_id' => $this->payload['id'],
                //         'te_id' => $emp
                //     ];
                // }

                // ## Insert employee schedule
                // $employeeSchedule = ScheduleEmployeeModel::insertOrDelete($employeesPayload);

                // if (!$employeeSchedule) {
                //     $tableName = ScheduleEmployeeModel::tableName();
                //     throw new Exception("Failed when insert data into table \"{$tableName}\"");
                // }

                // Return transaction status
                return (object) [
                    'status' => true,
                ];
            });
        } catch (Exception $e) {

            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Function to insert data from database
     * @return object|bool
     */
    public function deleteData()
    {
        ## Formatting additional data which not payload
        // Code here...

        ## Formatting payload
        // Code here...

        try {

            return DB::transaction(function () {

                ## Delete valid region
                $deleteData = ScheduleModel::find($this->payload['id'])->delete();

                if (!$deleteData) {
                    $tableName = ScheduleModel::tableName();
                    throw new Exception("Failed when delete data into table \"{$tableName}\"");
                }

                // Return transaction status
                return (object) [
                    'status' => true,
                ];
            });
        } catch (Exception $e) {

            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
