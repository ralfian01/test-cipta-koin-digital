<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Consolidated\FixedAssets;

use App\Http\Controllers\REST\BaseREST;

class Get extends BaseREST
{
    public function __construct(?array $p = [], ?array $f = [], ?array $a = [])
    {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }
    protected $payloadRules = [
        'keyword' => 'nullable|string|min:2',
        'status' => 'nullable|string|in:IN_USE,SOLD,DISPOSED',
        'page' => 'nullable|integer|min:1',
        'per_page' => 'nullable|integer|min:1|max:100',
    ];
    protected $privilegeRules = [];
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
        $result = $dbRepo->getConsolidatedData();
        if ($result->status) return $this->respond(200, $result->data);
        return $this->error(500, ['reason' => $result->message]);
    }
}
