<?php

namespace App\Http\Controllers\REST\V1\Manage\EmployeePositions;

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
        'id' => 'required|integer|exists:positions,id',
        'business_id' => 'integer|exists:business,id',
        'parent_id' => 'nullable|integer|exists:positions,id',
        'role_id' => 'integer|exists:role,id',
        'name' => 'string|max:255',
        'description' => 'nullable|string',
    ];
    protected $privilegeRules = [];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }
    private function nextValidation()
    {
        if (array_key_exists('name', $this->payload)) {
            $businessId = $this->payload['business_id'] ?? DBRepo::findBusinessId($this->payload['id']);
            if (!DBRepo::isNameUniqueOnUpdate($this->payload['name'], $businessId, $this->payload['id'])) {
                return $this->error((new Errors)->setMessage(409, 'The position name has already been taken for this business.'));
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
