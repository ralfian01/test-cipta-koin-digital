<?php

namespace App\Http\Controllers\REST\V1\Manage\Schedule;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
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
        'machine_id' => '',
        'product_id' => '',
        'shift_code' => '',
        'production_date' => '',
        'expired_date' => '',
        'employee_ids' => 'array'
    ];

    /**
     * @var array Property that contains the privilege data
     */
    protected $privilegeRules = [
        'SCHEDULE_MANAGE_VIEW',
        'SCHEDULE_MANAGE_MODIFY',
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

        // Make sure schedule id is available
        if (!DBRepo::checkScheduleId($this->payload['id'])) {
            return $this->error(
                (new Errors)
                    ->setMessage(404, 'Schedule id not found')
                    ->setReportId('MSU1')
            );
        }

        // Make sure machine id is available
        if (isset($this->payload['machine_id'])) {
            if (!DBRepo::checkMachineId($this->payload['machine_id'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, 'Machine id not available')
                        ->setReportId('MSU1')
                );
            }
        }

        // Make sure product id is available
        if (isset($this->payload['product_id'])) {
            if (!DBRepo::checkProductId($this->payload['product_id'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, 'Product id not available')
                        ->setReportId('MSU2')
                );
            }
        }

        return $this->update();
    }

    /** 
     * Function to insert data 
     * @return object
     */
    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);

        $update = $dbRepo->updateData();

        if ($update->status) {
            return $this->respond(200);
        }

        return $this->error(500, [
            'reason' => $update->message
        ]);
    }
}
