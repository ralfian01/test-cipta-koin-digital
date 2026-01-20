<?php

namespace App\Http\Controllers\REST\V1\Manage\CustomerCategory;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\CustomerCategory\DBRepo;

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
     * @var array Property that contains the payload rules.
     * Aturan 'unique' yang kompleks akan ditangani di nextValidation().
     */
    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'name' => 'required|string|max:100', // Validasi dasar tetap di sini
        'description' => 'nullable|string',
    ];

    /**
     * @var array Property that contains the privilege data
     */
    protected $privilegeRules = [
        "CUSTOMER_CATEGORY_MANAGE_VIEW",
        "CUSTOMER_CATEGORY_MANAGE_INSERT",
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
     * Handle the next step of payload validation.
     * Di sinilah kita akan menangani validasi unik yang di-scope.
     * @return void
     */
    private function nextValidation()
    {
        // Panggil DBRepo untuk melakukan pengecekan unik secara manual
        if (!DBRepo::isNameUniqueInBusiness($this->payload['name'], $this->payload['business_id'])) {
            // Jika tidak unik, kembalikan error spesifik
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'The category name has already been taken for this business.')
                    ->setReportId('MCCI1') // Manage Customer Category Insert 1
            );
        }

        // Jika semua validasi lanjutan lolos, lanjutkan ke proses insert
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
            return $this->respond(201, ['id' => $insert->data->id]);
        }

        return $this->error(500, ['reason' => $insert->message]);
    }
}
