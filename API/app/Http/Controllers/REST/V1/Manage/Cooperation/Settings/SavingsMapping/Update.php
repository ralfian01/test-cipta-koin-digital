<?php

namespace App\Http\Controllers\REST\V1\Manage\Cooperation\Settings\SavingsMapping;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
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
        'mappings' => 'present|array', // 'present' berarti field harus ada, meski isinya array kosong
        'mappings.*.cooperation_savings_type_id' => 'required|integer|exists:cooperation_savings_types,id',
        'mappings.*.savings_account_id' => 'required|integer|exists:account_charts,id',
        'mappings.*.cash_account_id' => 'required|integer|exists:account_charts,id',
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
        list($isValid, $message) = DBRepo::validateMappingPayload(
            $this->payload['mappings'],
            $this->payload['business_id']
        );
        if (!$isValid) return $this->error((new Errors)->setMessage(409, $message));
        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->updateMappings();
        if ($result->status)
            return $this->respond(200);

        return $this->error(500, ['reason' => $result->message]);
    }
}
