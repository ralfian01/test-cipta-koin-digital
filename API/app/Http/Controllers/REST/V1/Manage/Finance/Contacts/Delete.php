<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Contacts;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

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
        'id' => 'required|integer|exists:finance_contacts,id',
    ];

    protected $privilegeRules = [
        "MANAGE_FINANCE_CONTACT_VIEW",
        "MANAGE_FINANCE_CONTACT_DELETE"
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    /**
     * nextValidation sekarang memanggil static method dari DBRepo.
     */
    private function nextValidation()
    {
        return $this->delete();
    }

    public function delete()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->deleteData();
        if ($result->status) {
            return $this->respond(200);
        }
        // Gunakan 409 Conflict jika tidak bisa dihapus karena sudah ada transaksi
        return $this->error(409, ['reason' => $result->message]);
    }
}
