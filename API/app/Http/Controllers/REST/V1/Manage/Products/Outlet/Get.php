<?php

namespace App\Http\Controllers\REST\V1\Manage\Products\Outlet;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Get extends BaseREST
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
        'outlet_id' => 'required|integer|exists:outlets,id',
        // Tambahkan parameter lain jika perlu (paginasi, keyword)
        'page' => 'nullable|integer|min:1',
        'per_page' => 'nullable|integer|min:1|max:100',
    ];

    protected $privilegeRules = [
        "PRODUCT_MANAGE_VIEW",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }
    private function nextValidation()
    {
        return $this->get();
    }

    public function get()
    {
        // Kita bisa gunakan DBRepo dari Produk karena logikanya sangat terkait
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->getProductsByOutlet();

        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        return $this->respond(200, null); // Kembalikan null jika outlet tidak punya produk
    }
}
