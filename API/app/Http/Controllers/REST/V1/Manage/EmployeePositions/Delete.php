<?php

namespace App\Http\Controllers\REST\V1\Manage\EmployeePositions;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\EmployeePositions\DBRepo;

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
        'id' => 'required|integer|exists:positions,id'
    ];

    protected $privilegeRules = [];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // --- PERUBAHAN KRUSIAL DI SINI ---
        // Panggil DBRepo untuk memeriksa apakah posisi sedang digunakan.
        if (DBRepo::isPositionInUse($this->payload['id'])) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'Cannot delete position: It is currently assigned to one or more employees.')
                    ->setReportId('MEPD1') // Manage Employee Position Delete 1
            );
        }
        // ------------------------------------

        return $this->delete();
    }

    public function delete()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $delete = $dbRepo->deleteData();

        if ($delete->status) {
            return $this->respond(200);
        }

        return $this->error(500, ['reason' => $delete->message]);
    }
}
