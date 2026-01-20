<?php

namespace App\Http\Controllers\REST\V1\Manage\Outlets;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Insert extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'name' => 'required|string|max:100',
        'contact' => 'nullable|string|max:30',
        'address' => 'nullable|string',
        'geolocation' => 'nullable|string',
        'is_active' => 'nullable|boolean',
    ];

    protected $privilegeRules = [
        "OUTLET_MANAGE_VIEW",
        "OUTLET_MANAGE_INSERT",
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
