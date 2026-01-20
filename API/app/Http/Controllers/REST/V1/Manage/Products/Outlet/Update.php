<?php

namespace App\Http\Controllers\REST\V1\Manage\Products\Outlet;

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
        'product_id' => 'required|integer|exists:products,product_id',
        'outlet_ids' => 'present|array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
    ];

    protected $privilegeRules = [
        "PRODUCT_MANAGE_VIEW",
        "PRODUCT_MANAGE_UPDATE",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // --- PERUBAHAN KRUSIAL DI SINI ---
        // Logika validasi sekarang didelegasikan sepenuhnya ke DBRepo.
        if (!empty($this->payload['outlet_ids'])) {
            if (!DBRepo::checkBusinessIdConsistency($this->payload['product_id'], $this->payload['outlet_ids'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, 'One or more outlets do not belong to the same business unit as the product.')
                        ->setReportId('MPOU1')
                );
            }
        }
        // ------------------------------------

        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->syncProductOutlets();

        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
