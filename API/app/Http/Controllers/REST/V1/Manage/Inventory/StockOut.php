<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class StockOut extends BaseREST
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
        'quantity' => 'required|integer|min:1',
        'issued_to' => 'nullable|string',
        'notes' => 'nullable|string',
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
        if (!DBRepo::isStockSufficient($this->payload['item_id'], $this->payload['quantity'])) {
            // Jika stok tidak cukup, kembalikan error 409 Conflict.
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'Insufficient total stock for the requested item.')
                    ->setReportId('MISO1')
            );
        }

        return $this->insert();
    }

    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->stockOut();
        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(409, ['reason' => $result->message]); // 409 Conflict jika stok tidak cukup
    }
}
