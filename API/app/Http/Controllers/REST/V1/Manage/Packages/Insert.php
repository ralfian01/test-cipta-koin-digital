<?php

namespace App\Http\Controllers\REST\V1\Manage\Packages;

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
        'business_id' => 'nullable|integer|exists:business,id|required_without:outlet_ids',
        'outlet_ids' => 'nullable|array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
        'name' => 'required|string|max:255',
        'sku' => 'nullable|string',
        'is_active' => 'nullable|boolean',
        'items' => 'required|array|min:1',
        'items.*.item_type' => 'required|string|in:VARIANT,RESOURCE',
        'items.*.item_id' => 'required|integer',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.unit_id' => 'nullable|integer|exists:units,unit_id',
        'pricing' => 'required|array|min:1',
        'pricing.*.customer_category_id' => 'nullable|integer|exists:customer_categories,id',
        'pricing.*.price' => 'required|numeric|min:0',
    ];

    protected $privilegeRules = [
        "PACKAGE_MANAGE_VIEW",
        "PACKAGE_MANAGE_INSERT",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        $validationResult = DBRepo::validateAndDetermineBusinessId($this->payload);
        if (!$validationResult->status) {
            return $this->error((new Errors)->setMessage(409, $validationResult->message));
        }
        $this->payload['business_id'] = $validationResult->business_id;

        // --- VALIDASI BARU DI SINI ---
        // Hanya validasi SKU jika dikirim
        if (isset($this->payload['sku'])) {
            if (!DBRepo::isSkuUniqueInBusiness($this->payload['sku'], $this->payload['business_id'])) {
                return $this->error((new Errors)->setMessage(409, 'The SKU has already been taken for this business unit.'));
            }
        }
        // -----------------------------

        if (!DBRepo::validatePackageItems($this->payload['items'])) {
            return $this->error((new Errors)->setMessage(400, 'One or more item_id in the items array is invalid.'));
        }

        return $this->insert();
    }

    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $r = $dbRepo->insertData();

        if ($r->status) {
            return $this->respond(201, ['id' => $r->data->id]);
        }

        return $this->error(500, ['reason' => $r->message]);
    }
}
