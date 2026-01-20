<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory\Items;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Insert extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'name' => 'required|string|max:255',
        'sku' => 'required|string|unique:inventory_items,sku',
        'category_id' => 'nullable|integer|exists:inventory_item_categories,id',
        'base_unit_id' => 'required|integer|exists:units,unit_id',
        'reorder_level' => 'nullable|integer|min:0',
    ];

    protected $privilegeRules = [
        "INVENTORY_MANAGE_VIEW",
        "INVENTORY_MANAGE_INSERT",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        if (!DBRepo::isSkuUniqueInBusiness($this->payload['sku'], $this->payload['business_id'])) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'The SKU has already been taken for this business.')
                    ->setReportId('MIII1')
            );
        }
        return $this->insert();
    }


    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $insert = $dbRepo->insertData();
        if ($insert->status) {
            return $this->respond(201, ['id' => $insert->data->id]);
        }
        return $this->error(500, ['reason' => $insert->message]);
    }
}
