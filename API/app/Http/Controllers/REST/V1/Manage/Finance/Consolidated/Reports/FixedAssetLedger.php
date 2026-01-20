<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Consolidated\Reports;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class FixedAssetLedger extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        // 'business_id' tidak diperlukan
        'end_date' => 'required|date_format:Y-m-d',
        'keyword' => 'nullable|string|min:2',
        'status' => 'nullable|string|in:IN_USE,SOLD,DISPOSED',
        'page' => 'nullable|integer|min:1',
        'per_page' => 'nullable|integer|min:1|max:100',
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
        $result = $dbRepo->getConsolidatedFaLedger();

        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
