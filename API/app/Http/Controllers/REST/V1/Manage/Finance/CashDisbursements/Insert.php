<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\CashDisbursements;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Finance\Settings\DBRepo as SettingsRepo;

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

    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'entry_date' => 'required|date_format:Y-m-d',
        'description' => 'required|string',
        'details' => 'required|array|min:1',
        // Validasi hanya untuk "sisi lawan" (Pendapatan, dll.)
        'details.*.account_chart_id' => 'required|integer|exists:account_charts,id',
        'details.*.amount' => 'required|numeric|min:0.01',
    ];

    protected $privilegeRules = [];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Panggil static method dari SettingsRepo
        $isSet = SettingsRepo::isDefaultCashAccountSet($this->payload['business_id']);

        if (!$isSet) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'Default cash account is not configured for this business unit. Please set it up in the finance settings.')
                    ->setReportId('MCDV1') // Manage Cash Receipt Validation 1
            );
        }

        return $this->insert();
    }


    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $insert = $dbRepo->insertData();
        if ($insert->status) {
            return $this->respond(201, ['journal_entry_id' => $insert->data->journal_entry_id]);
        }
        return $this->error(500, ['reason' => $insert->message]);
    }
}
