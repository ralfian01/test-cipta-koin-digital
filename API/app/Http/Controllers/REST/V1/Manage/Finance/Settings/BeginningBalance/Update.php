<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Settings\BeginningBalance;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Finance\JournalEntries\DBRepo as JournalRepo;

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
        'entry_date' => 'required|date_format:Y-m-d',
        'details' => 'required|array', // Boleh array kosong untuk menghapus
        'details.*.account_chart_id' => 'required_with:details|integer|exists:account_charts,id',
        'details.*.entry_type' => 'required_with:details|string|in:DEBIT,CREDIT',
        'details.*.amount' => 'required_with:details|numeric|min:0',
    ];

    protected $privilegeRules = [];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Hanya validasi keseimbangan jika ada detail yang dikirim
        if (!empty($this->payload['details'])) {
            if (!JournalRepo::validateBalance($this->payload['details'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(400, 'Debit and Credit totals do not match.')
                        ->setReportId('MFSBBU1')
                );
            }
        }
        return $this->update();
    }

    // Ganti nama method menjadi update
    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $update = $dbRepo->updateData();
        if ($update->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $update->message]);
    }
}
