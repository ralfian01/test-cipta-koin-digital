<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Reports;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class FixedAssetLedger extends BaseREST
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
     * --- CONTOH PENGGUNAAN ---
     * GET /manage/finance/reports/account-payable-ledger?business_id=1&account_chart_id=3&start_date=2025-09-01&end_date=2025-09-30
     */
    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'fixed_asset_id' => 'required|integer|exists:fixed_assets,id',
        'start_date' => 'required|date_format:Y-m-d',
        'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
    ];

    protected $privilegeRules = [];
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

        $result = $dbRepo->generateFixedAssetLedger();

        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
