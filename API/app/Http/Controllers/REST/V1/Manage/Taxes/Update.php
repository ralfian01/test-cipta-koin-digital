<?php

namespace App\Http\Controllers\REST\V1\Manage\Taxes;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Taxes\DBRepo;

class Update extends BaseREST
{
    public function __construct(
        ?array $payload = [],
        ?array $file = [],
        ?array $auth = [],
    ) {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'id' => 'required|integer|exists:taxes,id',
        'business_id' => 'integer|exists:business,id',
        'name' => 'string|max:100',
        'rate' => 'numeric|min:0',
        'type' => 'string|in:PERCENTAGE,FIXED',
        'description' => 'nullable|string',
        'is_active' => 'nullable|boolean',
    ];

    protected $privilegeRules = [
        "TAXES_MANAGE_VIEW",
        "TAXES_MANAGE_UPDATE",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        if (array_key_exists('name', $this->payload)) {
            $outletId = $this->payload['business_id'] ?? DBRepo::findBusinessIid($this->payload['id']);
            if (!DBRepo::isNameUniqueOnUpdate($this->payload['name'], $outletId, $this->payload['id'])) {
                return $this->error((new Errors)->setMessage(409, 'The tax name has already been taken for this outlet.'));
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
