<?php

namespace App\Http\Controllers\REST\V1\Manage\Packages;

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
        'id' => 'required|integer|exists:packages,id',
        'business_id' => 'integer|exists:business,id',
        'name' => 'string|max:255',
        'sku' => 'nullable|string', // Unique check di nextValidation
        'is_active' => 'nullable|boolean',
        'items' => 'array',
        'items.*.item_type' => 'required_with:items|string|in:VARIANT,RESOURCE',
        'pricing' => 'array',
        'pricing.*.customer_category_id' => 'required_with:pricing|integer|exists:customer_categories,id',
        'outlet_ids' => 'array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
    ];
    protected $privilegeRules = [
        "PACKAGE_MANAGE_VIEW",
        "PACKAGE_MANAGE_UPDATE",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Ambil business_id dari paket yang ada di database sebagai "jangkar"
        $packageBusinessId = DBRepo::findBusinessId($this->payload['id']);
        if (!$packageBusinessId) {
            return $this->error((new Errors)->setMessage(404, 'Package not found.'));
        }

        // Aturan #3: Cek keunikan SKU dalam lingkup bisnis yang sama
        if (array_key_exists('sku', $this->payload)) {
            if (!DBRepo::isSkuUniqueOnUpdate($this->payload['sku'], $packageBusinessId, $this->payload['id'])) {
                return $this->error((new Errors)->setMessage(409, 'The SKU has already been taken for this business unit.'));
            }
        }

        // Aturan #2: Cek konsistensi outlet_ids dengan business_id paket
        if (array_key_exists('outlet_ids', $this->payload)) {
            if (!DBRepo::validateOutletConsistency($packageBusinessId, $this->payload['outlet_ids'])) {
                return $this->error((new Errors)->setMessage(409, 'One or more outlets do not belong to the business unit of this package.'));
            }
        }

        // Validasi lain jika ada (misal: items)
        if (array_key_exists('items', $this->payload)) {
            if (!DBRepo::validatePackageItems($this->payload['items'])) {
                return $this->error((new Errors)->setMessage(400, 'One or more item_id in the items array is invalid.'));
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
