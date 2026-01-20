<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\FixedAssets;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
{
    public function __construct(?array $p = [], ?array $f = [], ?array $a = [])
    {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }

    protected $payloadRules = [
        'id' => 'required|integer|exists:fixed_assets,id', // Dari URI

        // Data Dasar Aset (Opsional)
        'asset_name' => 'string',
        'asset_code' => 'string', // Unique check di nextValidation
        'description' => 'nullable|string',
        'acquisition_date' => 'date',
        'acquisition_cost' => 'numeric|min:0',
        'asset_account_id' => 'integer|exists:account_charts,id',
        'bill_id' => 'nullable|integer|exists:bills,id',
        'status' => 'string|in:IN_USE,SOLD,DISPOSED',

        // Pengaturan Penyusutan (Opsional, kirim objek kosong {} untuk menghapus)
        'depreciation_setting' => 'nullable|array',
        'depreciation_setting.depreciation_start_date' => 'required_with:depreciation_setting|date',
        'depreciation_setting.useful_life_in_months' => 'required_with:depreciation_setting|integer|min:1',
        'depreciation_setting.salvage_value' => 'required_with:depreciation_setting|numeric|min:0',
        'depreciation_setting.expense_account_id' => 'required_with:depreciation_setting|integer|exists:account_charts,id',
        'depreciation_setting.accumulated_depreciation_account_id' => 'required_with:depreciation_setting|integer|exists:account_charts,id',
    ];

    protected $privilegeRules = [
        // "MANANGE_FIXED_ASSET_VIEW",
        // "MANANGE_FIXED_ASSET_UPDATE",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Validasi unik untuk kode aset (jika diubah)
        if (isset($this->payload['asset_code'])) {
            if (!DBRepo::isCodeUniqueOnUpdate($this->payload['asset_code'], $this->payload['id'])) {
                return $this->error((new Errors)
                        ->setMessage(409, 'The asset code has already been taken.')
                        ->setReportId('MFFAU1')
                );
            }
        }
        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->updateData();
        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
