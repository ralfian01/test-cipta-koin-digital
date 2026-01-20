<?php

namespace App\Http\Controllers\REST\V1\Manage\Roles;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
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
        'id' => 'required|integer|exists:role,id',
        'name' => 'string|max:100',
        'privilege_ids' => 'array',
        'privilege_ids.*' => 'integer|exists:privilege,id',
    ];
    protected $privilegeRules = [
        "ROLE_MANAGE_VIEW",
        "ROLE_MANAGE_UPDATE",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }
    private function nextValidation()
    {
        if (array_key_exists('name', $this->payload)) {
            if (!DBRepo::isNameUniqueOnUpdate($this->payload['name'], $this->payload['id'])) {
                return $this->error((new Errors)->setMessage(409, 'The role name has already been taken.'));
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
