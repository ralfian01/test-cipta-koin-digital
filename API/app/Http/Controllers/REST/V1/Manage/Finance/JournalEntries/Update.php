<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\JournalEntries;

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

    protected $payloadRules = [
        'id' => 'required|integer|exists:journal_entries,id',
        'entry_date' => 'date_format:Y-m-d',
        'description' => 'string',
        'reference_number' => 'nullable|string',
        'details' => 'array|min:2',
        'details.*.account_chart_id' => 'required_with:details|integer|exists:account_charts,id',
        'details.*.entry_type' => 'required_with:details|string|in:DEBIT,CREDIT',
        'details.*.amount' => 'required_with:details|numeric|min:0.01',
    ];

    protected $privilegeRules = [
        "JOURNAL_MANAGE_VIEW",
        "JOURNAL_MANAGE_UPDATE",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        if (isset($this->payload['details'])) {
            if (!DBRepo::validateBalance($this->payload['details'])) {
                return $this->error((new Errors)->setMessage(400, 'Debit and Credit totals do not match.'));
            }
        }
        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->updateData();
        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
