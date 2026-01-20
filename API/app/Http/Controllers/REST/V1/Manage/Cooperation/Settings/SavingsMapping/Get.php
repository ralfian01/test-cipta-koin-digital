<?php

namespace App\Http\Controllers\REST\V1\Manage\Cooperation\Settings\SavingsMapping;

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
        'business_id' => 'nullable|integer|exists:business,id',
        'code' => 'nullable|string|exists:cooperation_savings_types,code',
    ];

    protected $privilegeRules = [
        "MEMBER_MANAGE_VIEW",
        "MEMBER_MANAGE_UPDATE",
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
        $result = $dbRepo->getMappings();
        if ($result->status) return $this->respond(200, $result->data);
        return $this->error(500, ['reason' => $result->message]);
    }
}
