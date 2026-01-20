<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Contacts;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
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
        'id' => 'required|integer|exists:finance_contacts,id',
        'business_id' => 'nullable|integer|exists:business,id',
        'contact_type' => 'nullable|string|in:CUSTOMER,VENDOR',
        'name' => 'nullable|string|max:255',
        'email' => 'nullable|email',
        'phone' => 'nullable|string',
        'address' => 'nullable|string',
        'company_name' => 'nullable|string',
        'tax_id_number' => 'nullable|string',
    ];

    protected $privilegeRules = [
        "MANAGE_FINANCE_CONTACT_VIEW",
        "MANAGE_FINANCE_CONTACT_UPDATE"
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    /**
     * nextValidation sekarang memanggil static method dari DBRepo.
     */
    private function nextValidation()
    {
        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->updateData();
        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
