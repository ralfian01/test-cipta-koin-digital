<?php

namespace App\Http\Controllers\REST\V1\Manage\EmployeePositions;

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
        'business_id' => 'required|integer|exists:business,id',
        'parent_id' => 'nullable|integer|exists:positions,id',
        'role_id' => 'required|integer|exists:role,id',
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
    ];
    protected $privilegeRules = [];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }
    private function nextValidation()
    {
        if (!DBRepo::isNameUniqueInBusiness($this->payload['name'], $this->payload['business_id'])) {
            return $this->error((new Errors)->setMessage(409, 'The position name has already been taken for this business.'));
        }
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
