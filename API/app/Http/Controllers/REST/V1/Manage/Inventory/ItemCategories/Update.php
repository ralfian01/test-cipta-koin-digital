<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory\ItemCategories;

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
        'id' => 'required|integer|exists:inventory_item_categories,id',
        'name' => 'string|max:255',
        'description' => 'nullable|string',
    ];
    protected $privilegeRules = [
        "INVENTORY_CATEGORY_MANAGE_VIEW",
        "INVENTORY_CATEGORY_MANAGE_UPDATE",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        if (array_key_exists('name', $this->payload)) {
            $businessId = DBRepo::findBusinessId($this->payload['id']);
            if (!DBRepo::isNameUniqueOnUpdate($this->payload['name'], $businessId, $this->payload['id'])) {
                return $this->error((new Errors)->setMessage(409, 'The category name has already been taken for this business.'));
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
