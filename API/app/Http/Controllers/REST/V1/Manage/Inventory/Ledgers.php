<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Ledgers extends BaseREST
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

    /**
     * @var array Property that contains the payload rules.
     * Contoh: /inventory/ledgers?item_id=2&start_date=2025-09-01&end_date=2025-09-30
     */
    protected $payloadRules = [
        'item_id' => 'nullable|integer|exists:inventory_items,id',
        'batch_id' => 'nullable|integer|exists:inventory_batches,id',
        'movement_type' => 'nullable|string|in:STOCK_IN,STOCK_OUT,ADJUSTMENT',
        'start_date' => 'nullable|date_format:Y-m-d',
        'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        'page' => 'nullable|integer|min:1',
        'per_page' => 'nullable|integer|min:1|max:100',
    ];

    protected $privilegeRules = [
        "INVENTORY_MANAGE_VIEW",
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
        $result = $dbRepo->getLedgers();

        if ($result->status) {
            return $this->respond(200, $result->data);
        }

        return $this->respond(200, null);
    }
}
