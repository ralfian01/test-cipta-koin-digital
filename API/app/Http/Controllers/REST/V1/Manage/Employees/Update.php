<?php

namespace App\Http\Controllers\REST\V1\Manage\Employees;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
{
    public function __construct(?array $p = [], ?array $f = [], ?array $a = [])
    {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }

    protected $payloadRules = [
        'id' => 'required|integer|exists:employees,id',
        'account_id' => 'nullable|integer|exists:account,id', // Untuk assign akun
        'name' => 'string|max:100',
        'phone_number' => 'nullable|string',
        'pin' => 'nullable|string|digits:6|confirmed',
        'address' => 'nullable|string',
        'is_active' => 'nullable|boolean',
        'outlet_ids' => 'nullable|array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
    ];

    protected $privilegeRules = [
        "EMPLOYEE_MANAGE_VIEW",
        "EMPLOYEE_MANAGE_UPDATE",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Validasi unik untuk account_id jika dikirim
        if (array_key_exists('account_d', $this->payload)) {
            if (!DBRepo::isAccountIdAvailable($this->payload['account_d'], $this->payload['id'])) {
                return $this->error((new Errors)->setMessage(409, 'The account has already been taken.'));
            }
        }

        // Kita tidak lagi memerlukan validasi account_id karena sudah terhubung
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
