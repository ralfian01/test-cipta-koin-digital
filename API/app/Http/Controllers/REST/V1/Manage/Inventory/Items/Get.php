<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory\Items;

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
        'id' => 'nullable|integer|exists:inventory_items,id',
        'business_id' => 'required_without:id|integer|exists:business,id',
        'category_id' => 'nullable|integer|exists:inventory_item_categories,id',
        'keyword' => 'nullable|string|min:2',
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
        $result = $dbRepo->getData();
        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        if (isset($this->payload['id'])) {
            return $this->error((new Errors)->setMessage(404, 'Inventory item not found.'));
        }
        return $this->respond(200, null);
    }
}
