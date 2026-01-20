<?php

namespace App\Http\Controllers\REST\V1\Manage\Cooperation\Settings\SavingsTypes;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\V1\Blueprint\Cooperation\Settings\SavingsTypes\Get as GetSavingsTypes;

class Get extends GetSavingsTypes
{
    public function __construct(?array $p = [], ?array $f = [], ?array $a = [])
    {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }

    protected $privilegeRules = [
        // "COOP_SAVING_TYPE_VIEW",
    ];
}
