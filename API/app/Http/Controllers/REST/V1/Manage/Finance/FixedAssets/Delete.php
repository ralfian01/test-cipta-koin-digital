<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\FixedAssets;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Finance\FixedAssets\DBRepo;

class Delete extends BaseREST
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
        'id' => 'required|integer|exists:fixed_assets,id',
    ];

    protected $privilegeRules = [];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Validasi bisnis: Pastikan aset belum pernah disusutkan.
        if (!DBRepo::canAssetBeDeleted($this->payload['id'])) {
            return $this->error((new Errors)
                    ->setMessage(409, 'Cannot delete asset: It has posted depreciation schedules. Consider changing its status to Disposed instead.')
                    ->setReportId('MFFAD1')
            );
        }
        return $this->delete();
    }

    public function delete()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->deleteData();

        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
