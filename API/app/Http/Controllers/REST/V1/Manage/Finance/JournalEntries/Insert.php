<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\JournalEntries;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Insert extends BaseREST
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
        'entry_date' => 'required|date_format:Y-m-d',
        'description' => 'required|string',
        'reference_number' => 'nullable|string',
        'details' => 'required|array|min:1',
        'details.*.account_chart_id' => 'required|integer|exists:account_charts,id',
        'details.*.entry_type' => 'required|string|in:DEBIT,CREDIT',
        'details.*.amount' => 'required|numeric|min:0.01',
    ];
    protected $privilegeRules = [
        "JOURNAL_MANAGE_VIEW",
        "JOURNAL_MANAGE_INSERT",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }
    private function nextValidation()
    {
        // // Validasi Keseimbangan Debit/Kredit
        // if (!DBRepo::validateBalance($this->payload['details'])) {
        //     return $this->error((new Errors)->setMessage(400, 'Debit and Credit totals do not match.'));
        // }
        return $this->insert();
    }
    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $r = $dbRepo->insertData();
        if ($r->status) {
            return $this->respond(201, ['id' => $r->data->id]);
        }
        return $this->error(500, ['reason' => $r->message]);
    }
}
