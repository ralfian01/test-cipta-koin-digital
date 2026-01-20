<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Reports;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class CashFlow extends BaseREST
{
    public function __construct(?array $p = [], ?array $f = [], ?array $a = [])
    {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }
    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'start_date' => 'required|date_format:Y-m-d',
        'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
    ];
    protected $privilegeRules = [
        "FINANCE_REPORT_VIEW",
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
        $result = $dbRepo->generateCashFlowStatement();
        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
