<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Reports\Consolidated;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class FinancialRatio extends BaseREST
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
     * GET /manage/finance/reports/financial-ratios?business_id=1&start_date=2025-01-01&end_date=2025-12-31
     */
    protected $payloadRules = [
        'start_date' => 'required|date_format:Y-m-d',
        'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
    ];

    protected $privilegeRules = [
        "FINANCE_CONSO_REPORT_VIEW",
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
        $result = $dbRepo->generateConsolidatedRatios();
        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
