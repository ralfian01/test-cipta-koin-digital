<?php

namespace App\Http\Controllers\REST\V1\Manage\Schedule;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Get extends BaseREST
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
        'machine_code' => '',
        'production_date' => '',
        'product_id' => ''
    ];

    /**
     * @var array Property that contains the privilege data
     */
    protected $privilegeRules = [
        'SCHEDULE_MANAGE_VIEW',
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
        return $this->get();
    }

    /** 
     * Function to get data 
     * @return object
     */
    public function get()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);

        $getData = $dbRepo->getData();

        ## If id not found
        if (!$getData->status) {
            return $this->error(
                (new Errors)->setMessage(404, "Data not found")
            );
        }

        return $this->respond(200, $getData->data);
    }
}
