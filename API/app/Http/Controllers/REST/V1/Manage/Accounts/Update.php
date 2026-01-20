<?php

namespace App\Http\Controllers\REST\V1\Manage\Accounts;

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
        'id' => 'required|integer|exists:account,id',
        'username' => 'string|max:50',
        'password' => 'nullable|string|min:8|confirmed',
        'role_ids' => 'array',
        'role_ids.*' => 'integer|exists:role,id',
        'status_active' => 'nullable|boolean',
    ];
    protected $privilegeRules = [
        "ACCOUNT_MANAGE_VIEW",
        "ACCOUNT_MANAGE_UPDATE"
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }
    private function nextValidation()
    {
        if (array_key_exists('username', $this->payload)) {
            if (!DBRepo::isUsernameUniqueOnUpdate($this->payload['username'], $this->payload['id'])) {
                return $this->error((new Errors)->setMessage(409, 'The username has already been taken.'));
            }
        }
        return $this->update();
    }
    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $r = $dbRepo->updateData();
        if ($r->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $r->message]);
    }
}
