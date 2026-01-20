<?php

namespace App\Http\Controllers\REST\V1\Manage\Products;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Products\DBRepo;

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

    /**
     * @var array Property that contains the payload rules.
     * Kita hanya perlu memvalidasi parameter 'product_id' dari URI.
     */
    protected $payloadRules = [
        'product_id' => 'required|integer|exists:products,product_id',
    ];

    protected $privilegeRules = [
        "PRODUCT_MANAGE_VIEW",
        "PRODUCT_MANAGE_DELETE",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Aturan 'exists' sudah memastikan ID valid, jadi kita bisa langsung lanjut.
        return $this->delete();
    }

    /** 
     * Function to delete data 
     * @return object
     */
    public function delete()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $delete = $dbRepo->deleteData();

        if ($delete->status) {
            // HTTP 200 OK atau 204 No Content adalah respons yang baik untuk delete.
            // Kita gunakan 200 untuk konsistensi, bisa juga 204 jika tidak ada body respons.
            return $this->respond(200);
        }

        return $this->error(500, ['reason' => $delete->message]);
    }
}
