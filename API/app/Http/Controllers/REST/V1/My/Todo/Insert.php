<?php

namespace App\Http\Controllers\REST\V1\My\Todo;

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
     * @var array Property that contains the payload rules.
     * Aturan 'unique' yang kompleks akan ditangani di nextValidation().
     */
    protected $payloadRules = [
        'title' => 'required|string|max:250',
        'description' => 'nullable|string',
    ];

    /**
     * @var array Property that contains the privilege data
     */
    protected $privilegeRules = [
        "TODO_VIEW",
        "TODO_INSERT",
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
            return $this->respond(201, $insert->data);
        }

        return $this->error(500, ['reason' => $insert->message]);
    }
}
