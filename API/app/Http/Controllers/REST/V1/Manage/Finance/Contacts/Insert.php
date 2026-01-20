<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Contacts;

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

    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'contact_type' => 'required|string|in:CUSTOMER,VENDOR',
        'name' => 'required|string|max:255',
        'email' => 'nullable|email',
        'phone' => 'nullable|string',
        'address' => 'nullable|string',
        'company_name' => 'nullable|string',
        'tax_id_number' => 'nullable|string',
    ];

    protected $privilegeRules = [
        "MANAGE_FINANCE_CONTACT_VIEW",
        "MANAGE_FINANCE_CONTACT_INSERT"
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
        $result = $dbRepo->insertData();
        if ($result->status) {
            return $this->respond(201, ['id' => $result->data->id]);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
