<?php

namespace App\Http\Controllers\REST\V1\Manage\Accounts;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Get extends BaseREST
{
    public function __construct(
        ?array $p = [],
        ?array $f = [],
        ?array $a = []
    ) {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }
    protected $payloadRules = [
        'id' => 'nullable|integer|exists:account,id',
        'keyword' => 'nullable|string|min:2',
        'page' => 'nullable|integer|min:1',
        'per_page' => 'nullable|integer|min:1|max:100',
    ];
    protected $privilegeRules = [
        "ACCOUNT_MANAGE_VIEW"
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
        $r = $dbRepo->getData();
        if ($r->status) {
            return $this->respond(200, $r->data);
        }
        if (isset($this->payload['id'])) {
            return $this->error((new Errors)->setMessage(404, 'Account not found.'));
        }
        return $this->respond(200, null);
    }
}
