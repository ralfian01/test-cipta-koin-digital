<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Contacts;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Get extends BaseREST
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
     */
    protected $payloadRules = [
        'id' => 'nullable|integer|exists:finance_contacts,id',
        'business_id' => 'required_without:id|integer|exists:business,id',
        'contact_type' => 'nullable|string|in:CUSTOMER,VENDOR',
        'keyword' => 'nullable|string',
        'page' => 'nullable|integer',
        'per_page' => 'nullable|integer',
    ];

    protected $privilegeRules = [
        "MANAGE_FINANCE_CONTACT_VIEW"
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }
    private function nextValidation()
    {
        return $this->get();
    }

    public function get()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->getData();
        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        if (isset($this->payload['id'])) {
            return $this->error(404);
        }
        return $this->respond(200, null);
    }
}
