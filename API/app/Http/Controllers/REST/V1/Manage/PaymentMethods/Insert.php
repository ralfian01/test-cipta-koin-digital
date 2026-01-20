<?php

namespace App\Http\Controllers\REST\V1\Manage\PaymentMethods;

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
        'name' => 'required|string|max:100|unique:payment_methods,name',
        'type' => 'required|string|in:CASH,EDC,QRIS,OTHER',
        'logo' => 'nullable|string',
        'description' => 'nullable|string',
        'is_active' => 'nullable|boolean',
    ];

    protected $privilegeRules = [
        "PAYMENT_METHOD_VIEW",
        "PAYMENT_METHOD_INSERT",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Validasi 'unique' sudah ditangani, bisa langsung lanjut.
        return $this->insert();
    }

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
