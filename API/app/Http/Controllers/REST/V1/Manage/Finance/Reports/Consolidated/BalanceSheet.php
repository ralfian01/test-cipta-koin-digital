<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Reports\Consolidated;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class BalanceSheet extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'end_date' => 'required|date_format:Y-m-d',
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
        $result = $dbRepo->generateConsolidatedBalanceSheet();
        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
