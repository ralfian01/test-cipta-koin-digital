<?php

namespace App\Http\Controllers\REST\V1\Manage\Roles;

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
    protected $payloadRules = [
        'id' => 'nullable|integer|exists:tbl_role,id'
    ];
    protected $privilegeRules = [
        "ROLE_MANAGE_VIEW",
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
        $r = $dbRepo->getData();
        if ($r->status) {
            return $this->respond(200, $r->data);
        }
        if (isset($this->payload['id'])) {
            return $this->error((new Errors)->setMessage(404, 'Role not found.'));
        }
        return $this->respond(200, null);
    }
}
