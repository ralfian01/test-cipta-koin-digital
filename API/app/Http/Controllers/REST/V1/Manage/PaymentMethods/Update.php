<?php

namespace App\Http\Controllers\REST\V1\Manage\Product;

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
        'name' => '',
        'weight' => '',
        'expired_duration' => '',
        'image' => 'file|mimes:jpg,jpeg,png',
        'image_url' => 'string',
    ];

    /**
     * @var array Property that contains the privilege data
     */
    protected $privilegeRules = [
        'PRODUCT_MANAGE_VIEW',
        'PRODUCT_MANAGE_MODIFY',
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

        // Make sure product id is available
        if (!DBRepo::checkProductId($this->payload['id'])) {
            return $this->error(
                (new Errors)
                    ->setMessage(404, 'Product id not found')
                    ->setReportId('MPU1')
            );
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
