<?php

namespace App\Http\Controllers\REST\V1\Manage\Schedule;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Insert extends BaseREST
{
    public function __construct(
        ?array $payload = [],
        ?array $file = [],
        ?array $auth = [],
    ) {

        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    /**
     * @var array Property that contains the payload rules
     */
    protected $payloadRules = [
        'machine_id' => 'required',
        'product_id' => 'required',
        'shift_code' => 'required',
        'production_date' => 'required',
        'expired_date' => 'required',
        'employee_ids' => 'required|array'
    ];

    /**
     * @var array Property that contains the privilege data
     */
    protected $privilegeRules = [
        'SCHEDULE_MANAGE_VIEW',
        'SCHEDULE_MANAGE_ADD',
    ];


    /**
     * The method that starts the main activity
     * @return null
     */
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    /**
     * Handle the next step of payload validation
     * @return void
     */
    private function nextValidation()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);

        // Make sure machine id is available
        if (!DBRepo::checkMachineId($this->payload['machine_id'])) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'Machine id not available')
                    ->setReportId('MSI1')
            );
        }

        // Make sure product id is available
        if (!DBRepo::checkProductId($this->payload['product_id'])) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'Product id not available')
                    ->setReportId('MSI2')
            );
        }

        // Make sure employee ids is available
        if (!DBRepo::checkEmployeeIds($this->payload['employee_ids'], $invalidIds)) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, "Employee id are not available")
                    ->setDetail([
                        'employee_ids' => 'The following employee id are not available: ' . implode(', ', $invalidIds)
                    ])
                    ->setReportId('MSI3')
            );
        }


        return $this->insert();
    }

    /** 
     * Function to insert data 
     * @return object
     */
    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);

        $insert = $dbRepo->insertData();

        if ($insert->status) {
            return $this->respond(200);
        }

        return $this->error(500, [
            'reason' => $insert->message
        ]);
    }
}
