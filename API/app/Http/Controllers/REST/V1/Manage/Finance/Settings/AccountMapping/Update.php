<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Settings\AccountMapping;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
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
     * --- CONTOH PAYLOAD ---
     * {
     *     "business_id": 1,
     *     "default_cash_account_id": 3
     * }
     */
    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'default_cash_account_id' => 'nullable|integer|exists:account_charts,id',
        'default_ar_account_id' => 'nullable|integer|exists:account_charts,id',
        'default_ap_account_id' => 'nullable|integer|exists:account_charts,id',
    ];


    protected $privilegeRules = [
        "FINANCE_SETTING_VIEW",
        "FINANCE_SETTING_UPDATE"
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        $businessId = $this->payload['business_id'];

        // Kumpulkan semua ID akun dari payload untuk divalidasi
        $accountsToCheck = [
            $this->payload['default_cash_account_id'] ?? null,
            $this->payload['default_ar_account_id'] ?? null,
            $this->payload['default_ap_account_id'] ?? null,
        ];

        // Panggil method validasi baru dari DBRepo
        if (!DBRepo::areAccountsValidForBusiness($accountsToCheck, $businessId)) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'One or more selected accounts do not belong to the specified business unit.')
                    ->setReportId('MFSV1')
            );
        }

        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->updateSettings();

        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
