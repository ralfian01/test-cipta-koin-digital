<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory\Items\Batches;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Get extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'id' => 'required|integer|exists:inventory_items,id', // ID dari item
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
        $result = $dbRepo->getBatchesByItem();
        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        return $this->respond(200, null);
    }
}
