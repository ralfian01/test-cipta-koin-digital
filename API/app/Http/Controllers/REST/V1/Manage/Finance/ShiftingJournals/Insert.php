<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\ShiftingJournals;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Finance\JournalEntries\DBRepo as JournalRepo;

class Insert extends BaseREST
{
    public function __construct(?array $p = [], ?array $f = [], ?array $a = [])
    {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }

    /**
     * @var array
     * --- CONTOH PAYLOAD ---
     * {
     *     "business_id": 1,
     *     "entry_date": "2025-10-15",
     *     "description": "Pergeseran dana dari Bendahara ke Kas Operasional",
     *     "reference_number": "MEMO-001",
     *     "details": [
     *         { "account_chart_id": 1, "entry_type": "CREDIT", "amount": 10000000 },
     *         { "account_chart_id": 3, "entry_type": "DEBIT", "amount": 10000000 }
     *     ]
     * }
     */
    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'entry_date' => 'required|date_format:Y-m-d',
        'description' => 'required|string',
        'reference_number' => 'nullable|string',
        'details' => 'required|array|min:2',
        'details.*.account_chart_id' => 'required|integer|exists:account_charts,id',
        'details.*.entry_type' => 'required|string|in:DEBIT,CREDIT',
        'details.*.amount' => 'required|numeric|min:0.01',
    ];

    protected $privilegeRules = [
        "SHIFTING_JOURNAL_MANAGE_VIEW",
        "SHIFTING_JOURNAL_MANAGE_INSERT",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Validasi Keseimbangan Debit/Kredit, menggunakan validator dari Jurnal Umum
        if (!JournalRepo::validateBalance($this->payload['details'])) {
            return $this->error(
                (new Errors)
                    ->setMessage(400, 'Debit and Credit totals for the shifting journal do not match.')
                    ->setReportId('MSJV1') // Manage Shifting Journal Validation 1
            );
        }
        return $this->insert();
    }

    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $insert = $dbRepo->insertData();
        if ($insert->status) {
            return $this->respond(201, ['id' => $insert->data->id]);
        }
        return $this->error(500, ['reason' => $insert->message]);
    }
}
