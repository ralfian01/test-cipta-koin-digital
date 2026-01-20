<?php

namespace App\Http\Controllers\REST\V1\Manage\Employees;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Insert extends BaseREST
{
    public function __construct(
        ?array $payload = [],
        ?array $file = [],
        ?array $auth = []
    ) {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    /**
     * @var array
     * --- CONTOH PAYLOAD BARU ---
     * {
     *     "name": "Budi Kasir",
     *     "phone_number": "08123456789",
     *     "username": "budikasir",
     *     "password": "password123",
     *     "password_confirmation": "password123",
     *     "pin": "123456",
     *     "pin_confirmation": "123456",
     *     "outlet_ids": [1, 2]
     * }
     */
    protected $payloadRules = [
        'name' => 'required|string|max:100',
        'business_id' => 'required|integer',
        'phone_number' => 'nullable|string',
        'pin' => 'nullable|string|digits:6|confirmed',
        'address' => 'nullable|string',
        'is_active' => 'nullable|boolean',
        'outlet_ids' => 'nullable|array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
    ];

    protected $privilegeRules = [
        "EMPLOYEE_MANAGE_VIEW",
        "EMPLOYEE_MANAGE_INSERT",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }
    private function nextValidation()
    {
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
