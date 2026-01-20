<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\ShiftingJournals;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Delete extends BaseREST
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
        'id' => 'required|integer|exists:shifting_journal_entries,id'
    ];

    protected $privilegeRules = [
        "SHIFTING_JOURNAL_MANAGE_VIEW",
        "SHIFTING_JOURNAL_MANAGE_DELETE",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        return $this->delete();
    }

    public function delete()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->deleteData();
        if ($result->status) return $this->respond(200);
        return $this->error(500, ['reason' => $result->message]);
    }
}
