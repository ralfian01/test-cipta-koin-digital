<?php

namespace App\Http\Controllers\REST\V1\Manage\Roles;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Insert extends BaseREST
{
    public function __construct(
        ?array $p = [],
        ?array $f = [],
        ?array $a = []
    ) {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }

    protected $payloadRules = [
        'name' => 'required|string|max:100|unique:role,name',
        'privilege_ids' => 'required|array|min:1',
        'privilege_ids.*' => 'integer|exists:privilege,id',
    ];

    protected $privilegeRules = [
        "ROLE_MANAGE_VIEW",
        "ROLE_MANAGE_INSERT",
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
