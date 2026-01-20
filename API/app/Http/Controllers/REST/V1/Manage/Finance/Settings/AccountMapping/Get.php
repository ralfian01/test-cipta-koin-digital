<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Settings\AccountMapping;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Get extends BaseREST
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
     * GET /manage/finance/settings?business_id=1
     */
    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
    ];

    protected $privilegeRules = [
        "FINANCE_SETTING_VIEW"
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
        $result = $dbRepo->getSettings();

        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        return $this->respond(200, null); // Kembalikan null jika belum ada pengaturan
    }
}
