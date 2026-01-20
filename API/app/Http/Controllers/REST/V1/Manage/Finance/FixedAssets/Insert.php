<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\FixedAssets;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Insert extends BaseREST
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
     * @var array
     * --- CONTOH PAYLOAD LENGKAP ---
     * {
     *     "business_id": 1,
     *     "asset_name": "Laptop Dell XPS 15",
     *     "acquisition_date": "2025-01-15",
     *     "acquisition_cost": 25000000,
     *     "asset_account_id": 9,
     *     "depreciation_setting": {
     *         "depreciation_start_date": "2025-01-31",
     *         "useful_life_in_months": 60,
     *         "salvage_value": 1000000,
     *         "expense_account_id": 45,
     *         "accumulated_depreciation_account_id": 11
     *     }
     * }
     * --- CONTOH PAYLOAD TANPA PENYUSUTAN ---
     * {
     *     "business_id": 1,
     *     "asset_name": "Tanah Kavling A",
     *     "acquisition_date": "2025-02-20",
     *     "acquisition_cost": 500000000,
     *     "asset_account_id": 7
     * }
     */
    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'asset_name' => 'required|string',
        'acquisition_date' => 'required|date',
        'acquisition_cost' => 'required|numeric|min:0',
        'asset_account_id' => 'required|integer|exists:account_charts,id',
        'bill_id' => 'nullable|integer|exists:bills,id',

        // Pengaturan Penyusutan (Opsional)
        'depreciation_setting' => 'nullable|array',
        'depreciation_setting.depreciation_start_date' => 'required_with:depreciation_setting|date',
        'depreciation_setting.useful_life_in_months' => 'required_with:depreciation_setting|integer|min:1',
        'depreciation_setting.salvage_value' => 'required_with:depreciation_setting|numeric|min:0',
        'depreciation_setting.expense_account_id' => 'required_with:depreciation_setting|integer|exists:account_charts,id',
        'depreciation_setting.accumulated_depreciation_account_id' => 'required_with:depreciation_setting|integer|exists:account_charts,id',
    ];

    protected $privilegeRules = [
        // "MANANGE_FIXED_ASSET_VIEW",
        // "MANANGE_FIXED_ASSET_INSERT",
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
        $result = $dbRepo->insertData();
        if ($result->status) {
            return $this->respond(201, ['id' => $result->data->id]);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
