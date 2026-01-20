<?php

namespace App\Http\Controllers\REST\V1\Manage\Accounts;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Insert extends BaseREST
{
    public function __construct(?array $p = [], ?array $f = [], ?array $a = [])
    {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }
    protected $payloadRules = [
        'username' => 'required|string|max:50|unique:account,username',
        'password' => 'required|string|min:8|confirmed',
        'role_ids' => 'required|array|min:1',
        'role_ids.*' => 'integer|exists:role,id',
        'status_active' => 'nullable|boolean',
    ];
    protected $privilegeRules = [
        "ACCOUNT_MANAGE_VIEW",
        "ACCOUNT_MANAGE_INSERT"
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
        $r = $dbRepo->insertData();
        if ($r->status) {
            return $this->respond(201, ['id' => $r->data->id]);
        }
        return $this->error(500, ['reason' => $r->message]);
    }
}
