<?php

namespace App\Http\Controllers\REST\V1\Manage\Promos\Outlet;

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
        'promo_id' => 'required|integer|exists:promos,id',
        'outlet_ids' => 'present|array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
    ];

    protected $privilegeRules = [
        "PROMO_MANAGE_VIEW",
        "PROMO_MANAGE_UPDATE",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        if (!empty($this->payload['outlet_ids'])) {
            if (!DBRepo::checkBusinessIdConsistency($this->payload['promo_id'], $this->payload['outlet_ids'])) {
                return $this->error((new Errors)->setMessage(409, 'One or more outlets do not belong to the same business unit as the promo.'));
            }
        }
        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->syncPromoOutlets();
        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
