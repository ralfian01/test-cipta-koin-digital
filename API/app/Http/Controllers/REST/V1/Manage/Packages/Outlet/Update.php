<?php

namespace App\Http\Controllers\REST\V1\Manage\Packages\Outlet;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'package_id' => 'required|integer|exists:packages,id',
        'outlet_ids' => 'present|array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
    ];

    protected $privilegeRules = [
        "PACKAGE_MANAGE_VIEW",
        "PACKAGE_MANAGE_UPDATE",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // --- PERUBAHAN KRUSIAL DI SINI ---
        if (!empty($this->payload['outlet_ids'])) {
            if (!DBRepo::checkBusinessIdConsistency($this->payload['package_id'], $this->payload['outlet_ids'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, 'One or more outlets do not belong to the same business unit as the package.')
                        ->setReportId('MPKU1')
                );
            }
        }
        // ------------------------------------

        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->syncPackageOutlets();
        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
