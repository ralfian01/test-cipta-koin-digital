<?php

namespace App\Http\Controllers\REST\V1\Manage\Units;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Insert extends BaseREST
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
        // 'name' wajib diisi dan harus unik di tabel 'units' pada kolom 'name'
        'name' => 'required|string|max:255|unique:units,name',
        'description' => 'nullable|string',
        'type' => 'required|in:QUANTITY,TIME',
        'value_in_seconds' => 'required_if:type,TIME|integer'
    ];

    /**
     * @var array Property that contains the privilege data
     */
    protected $privilegeRules = [
        "UNIT_MANAGE_VIEW",
        "UNIT_MANAGE_INSERT",
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
        return $this->insert();
    }

    /** 
     * Function to insert data 
     * @return object
     */
    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $insert = $dbRepo->insertData();

        if ($insert->status) {
            // Mengembalikan status 201 Created dan ID dari unit yang baru dibuat
            return $this->respond(201, ['unit_id' => $insert->data->unit_id]);
        }

        return $this->error(500, ['reason' => $insert->message]);
    }
}
