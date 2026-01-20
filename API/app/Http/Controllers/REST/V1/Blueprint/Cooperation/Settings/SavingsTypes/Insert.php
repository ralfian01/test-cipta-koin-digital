<?php

namespace App\Http\Controllers\REST\V1\Blueprint\Cooperation\Settings\SavingsTypes;

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
        'name' => 'required|string|max:255|unique:cooperation_savings_types,name',
        'code' => 'required|string|max:50|unique:cooperation_savings_types,code|alpha_dash',
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
        return $this->insert();
    }

    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->insertData();

        if ($result->status)
            return $this->respond(201, ['id' => $result->data->id]);

        return $this->error(500, ['reason' => $result->message]);
    }
}
