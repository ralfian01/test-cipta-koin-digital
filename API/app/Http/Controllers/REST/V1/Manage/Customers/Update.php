<?php

namespace App\Http\Controllers\REST\V1\Manage\Customers;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'id' => 'required|integer|exists:customers,id',
        'business_id' => 'integer|exists:business,id',
        'customer_category_id' => 'nullable|integer|exists:customer_categories,id',
        'name' => 'string|max:100',
        'phone_number' => 'string|max:255',
        'email' => 'nullable|email',
        'address' => 'nullable|string',
    ];

    protected $privilegeRules = [
        "CUSTOMER_MANAGE_VIEW",
        "CUSTOMER_MANAGE_UPDATE",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        $businessId = $this->payload['business_id'] ?? DBRepo::findBusinessId($this->payload['id']);

        if (array_key_exists('phone_number', $this->payload)) {
            if (!DBRepo::isPhoneNumberUniqueOnUpdate($this->payload['phone_number'], $businessId, $this->payload['id'])) {
                return $this->error((new Errors)->setMessage(409, 'The phone number has already been taken.'));
            }
        }
        if (array_key_exists('email', $this->payload)) {
            if (!DBRepo::isEmailUniqueOnUpdate($this->payload['email'], $businessId, $this->payload['id'])) {
                return $this->error((new Errors)->setMessage(409, 'The email has already been taken.'));
            }
        }
        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $update = $dbRepo->updateData();

        if ($update->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $update->message]);
    }
}
