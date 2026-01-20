<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class StockIn extends BaseREST
{
    public function __construct(?array $p = [], ?array $f = [], ?array $a = [])
    {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }

    protected $payloadRules = [
        'item_id' => 'required|integer|exists:inventory_items,id',
        'quantity_received' => 'required|integer|min:1',
        'unit_cost' => 'required|numeric|min:0',
        'received_date' => 'required|date_format:Y-m-d',
        'notes' => 'nullable|string',
        'expiration_date' => 'nullable|date_format:Y-m-d|after_or_equal:received_date',
    ];

    protected $privilegeRules = [
        "INVENTORY_MANAGE_VIEW",
        "INVENTORY_MANAGE_STOCK_UPDATE",
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
        $result = $dbRepo->stockIn();
        if ($result->status) {
            return $this->respond(201, ['batch_id' => $result->data->id]);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
