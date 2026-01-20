<?php

namespace App\Http\Controllers\REST\V1\Manage\Units;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Delete extends BaseREST
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
     * @var array Property that contains the payload rules
     *
     * --- CONTOH PAYLOAD ---
     * {
     *     "name": "Box",
     *     "description": "Satuan untuk barang per box / kardus."
     * }
     */
    protected $payloadRules = [
        'unit_id' => 'required|integer|exists:units,unit_id',
    ];

    /**
     * @var array Property that contains the privilege data
     */
    protected $privilegeRules = [
        "UNIT_MANAGE_VIEW",
        "UNIT_MANAGE_DELETE",
    ];

    /**
     * The method that starts the main activity
     * @return null
     */
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    /**
     * Handle the next step of payload validation
     * @return void
     */
    private function nextValidation()
    {
        // Karena validasi 'unique' sudah ditangani oleh $payloadRules,
        // tidak ada validasi lanjutan yang diperlukan di sini.
        // Kita bisa langsung melanjutkan ke proses insert.
        return $this->delete();
    }

    /** 
     * Function to insert data 
     * @return object
     */
    public function delete()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $insert = $dbRepo->deleteData();

        if ($insert->status) {
            // Mengembalikan status 201 Created dan ID dari unit yang baru dibuat
            return $this->respond(200);
        }

        return $this->error(500, ['reason' => $insert->message]);
    }
}
