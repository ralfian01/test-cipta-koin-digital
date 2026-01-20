<?php

namespace App\Http\Controllers\REST\V1\Manage\Customers;

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
        'customer_category_id' => 'nullable|integer|exists:customer_categories,id',
        'name' => 'required|string|max:100',
        'phone_number' => 'required|string|max:255',
        'email' => 'nullable|email',
        'address' => 'nullable|string',
    ];

    protected $privilegeRules = [
        "CUSTOMER_MANAGE_VIEW",
        "CUSTOMER_MANAGE_INSERT",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Validasi unik untuk phone_number, di-scope per business_id
        if (!DBRepo::isPhoneNumberUniqueInBusiness($this->payload['phone_number'], $this->payload['business_id'])) {
            return $this->error((new Errors)->setMessage(409, 'The phone number has already been taken for this business.'));
        }

        // Validasi unik untuk email (jika ada), di-scope per business_id
        if (isset($this->payload['email'])) {
            if (!DBRepo::isEmailUniqueInBusiness($this->payload['email'], $this->payload['business_id'])) {
                return $this->error((new Errors)->setMessage(409, 'The email has already been taken for this business.'));
            }
        }

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
