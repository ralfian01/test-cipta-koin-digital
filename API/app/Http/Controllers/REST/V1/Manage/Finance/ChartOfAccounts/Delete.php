<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\ChartOfAccounts;

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
        'id' => 'required|integer|exists:account_charts,id',
    ];

    protected $privilegeRules = [
        "ACCOUNT_CHART_MANAGE_VIEW",
        "ACCOUNT_CHART_MANAGE_DELETE",
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
        if ($result->status) {
            return $this->respond(200);
        }
        // Error 409 (Conflict) lebih cocok jika akun tidak bisa dihapus
        return $this->error(409, ['reason' => $result->message]);
    }
}
