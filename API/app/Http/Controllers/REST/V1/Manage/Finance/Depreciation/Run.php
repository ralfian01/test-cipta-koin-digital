<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Depreciation;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Run extends BaseREST
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
     *     "period_date": "2025-10-31"
     * }
     */
    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'period_date' => 'required|date_format:Y-m-d',
    ];

    protected $privilegeRules = [];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }
    private function nextValidation()
    {
        return $this->run();
    }

    /**
     * Nama method disesuaikan dengan nama kelas.
     */
    public function run()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->runMonthlyDepreciation();

        if ($result->status) {
            $message = "Depreciation process completed successfully. {$result->data->journals_created} journal entries were created.";
            return $this->respond(200, [
                'message' => $message,
                'details' => $result->data
            ]);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
