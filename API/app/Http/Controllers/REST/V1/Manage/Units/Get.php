<?php

namespace App\Http\Controllers\REST\V1\Manage\Units;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Get extends BaseREST
{
    public function __construct(
        ?array $payload = [],
        ?array $file = [],
        ?array $auth = [],
    ) {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    /**
     * @var array Property that contains the payload rules.
     * Contoh: /manage/units atau /manage/units?id=1
     */
    protected $payloadRules = [
        // Primary key untuk tabel units adalah 'unit_id'
        'id' => 'nullable|integer|exists:units,unit_id',
        'keyword' => 'nullable|string|min:3',
        'type' => 'nullable|in:QUANTITY,TIME',
        'page' => 'nullable|integer|min:1',
        'per_page' => 'nullable|integer|min:1|max:100',
    ];

    protected $privilegeRules = [
        "UNIT_MANAGE_VIEW",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Validasi 'exists' sudah cukup, bisa langsung lanjut.
        return $this->get();
    }

    /** 
     * Function to get data 
     * @return object
     */
    public function get()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->getData();

        if ($result->status) {
            return $this->respond(200, $result->data);
        }

        // Jika ID spesifik diminta tapi tidak ditemukan, kembalikan 404
        if (isset($this->payload['id'])) {
            return $this->error((new Errors)->setMessage(404, 'Unit not found.'));
        }

        // Jika daftar kosong (misal hasil search 0), kembalikan data null
        return $this->respond(200, null);
    }
}
