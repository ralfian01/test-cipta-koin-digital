<?php

namespace App\Http\Controllers\REST\V1\Blueprint\Cooperation\Settings\SavingsTypes;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

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
        'id' => 'nullable|integer|exists:cooperation_savings_types,id',
        'code' => 'nullable|string|exists:cooperation_savings_types,code',
    ];

    protected $privilegeRules = [
        "BP_COOP_SAVING_TYPE_VIEW",
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
        $result = $dbRepo->getData();
        if ($result->status) return $this->respond(200, $result->data);
        if (isset($this->payload['id']))
            return $this->error((new Errors)->setMessage(404, 'Savings type not found.'));
        return $this->respond(200, null);
    }
}
