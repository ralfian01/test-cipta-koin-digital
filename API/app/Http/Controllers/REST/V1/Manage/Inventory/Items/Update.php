<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory\Items;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'id' => 'required|integer|exists:inventory_items,id',
        'name' => 'string|max:255',
        'sku' => 'string',
        'category_id' => 'nullable|integer|exists:inventory_item_categories,id',
        'base_unit_id' => 'required|integer|exists:units,unit_id',
        'reorder_level' => 'nullable|integer|min:0',
    ];

    protected $privilegeRules = [
        "INVENTORY_MANAGE_VIEW",
        "INVENTORY_MANAGE_UPDATE",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        if (array_key_exists('sku', $this->payload)) {
            $businessId = DBRepo::findBusinessId($this->payload['id']);
            if (!DBRepo::isSkuUniqueOnUpdate($this->payload['sku'], $businessId, $this->payload['id'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, 'The SKU has already been taken for this business.')
                        ->setReportId('MIIU1')
                );
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
