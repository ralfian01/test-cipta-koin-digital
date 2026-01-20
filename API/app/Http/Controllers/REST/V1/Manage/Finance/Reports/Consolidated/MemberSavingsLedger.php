<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Reports\Consolidated;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class MemberSavingsLedger extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'member_id' => 'required|integer|exists:members,id',
        'cooperation_savings_type_id' => 'required|integer|exists:cooperation_savings_types,id',
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
        $result = $dbRepo->generateConsolidatedMemberSavingsLedger();

        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
