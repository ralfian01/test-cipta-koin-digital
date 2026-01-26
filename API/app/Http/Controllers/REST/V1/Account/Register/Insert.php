<?php

namespace App\Http\Controllers\REST\V1\Account\Register;

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
        'username' => 'required|string|unique:account,username',
        'password' => 'required|string',
    ];

    /**
     * @var array Property that contains the privilege data
     */
    protected $privilegeRules = [];


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
        return $this->insert();
    }

    /** 
     * Function to get data 
     * @return object
     */
    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);

        $insert = $dbRepo->insertAccount();

        ## If id not found
        if (!$insert->status) {
            return $this->error(
                (new Errors)->setMessage(404, "Data not found")
            );
        }

        return $this->respond(201);
    }
}
