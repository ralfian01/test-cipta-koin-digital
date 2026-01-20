<?php

namespace App\Http\Controllers\REST\V1\Manage\Products;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Products\DBRepo;

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

    /**
     * @var array
     * --- CONTOH PAYLOAD UPDATE ---
     * {
     *     "name": "Kaos Polo Official Premium", // Mengubah nama produk
     *     "variants": [
     *         {
     *             "variant_id": 1, // Ada ID -> Update varian yang ada
     *             "name": "Biru Dongker, L",
     *             "stock_quantity": 90,
     *             "pricing": [
     *                 { "price_id": 1, "price": 260000 }, // Ada ID -> Update harga
     *                 { "customer_category_id": 2, "price": 235000 } // Tidak ada ID -> Buat harga baru
     *             ]
     *         },
     *         {
     *             // Tidak ada variant_id -> Buat varian baru
     *             "name": "Merah, M", "stock_quantity": 75,
     *             "pricing": [ { "customer_category_id": 1, "price": 260000 } ]
     *         }
     *     ]
     * }
     */
    protected $payloadRules = [
        'product_id' => 'required|integer|exists:products,product_id',

        // Informasi Dasar Produk (opsional)
        'business_id' => 'integer|exists:business,id',
        'outlet_ids' => 'nullable|array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
        'category_id' => 'nullable|integer|exists:product_categories,id',
        'name' => 'string|max:255',

        // --- PERBAIKAN DI SINI ---
        // Aturan untuk Produk KONSUMSI
        'variants' => 'array',
        'variants.*.variant_id' => 'nullable|integer|exists:product_variants,variant_id',
        'variants.*.name' => 'required|string|max:255', // Cukup 'required'
        'variants.*.stock_quantity' => 'nullable|integer|min:0',
        'variants.*.pricing' => 'required|array|min:1', // Cukup 'required'
        'variants.*.pricing.*.price_id' => 'nullable|integer|exists:pricing,price_id',
        'variants.*.pricing.*.customer_category_id' => 'nullable|integer|exists:customer_categories,id',
        'variants.*.pricing.*.price' => 'required|numeric|min:0',

        // Aturan untuk Produk SEWA
        'resources' => 'array',
        'resources.*.resource_id' => 'nullable|integer|exists:resources,resource_id',
        'resources.*.name' => 'required|string|max:255', // Cukup 'required'
        'resources.*.availability' => 'required|array', // Cukup 'required'
        'resources.*.availability.*.day_of_week' => 'required|integer|between:0,6',
        'resources.*.availability.*.start_time' => 'required|date_format:H:i',
        'resources.*.availability.*.end_time' => 'required|date_format:H:i|after:resources.*.availability.*.start_time',
        'resources.*.pricing' => 'required|array|min:1', // Cukup 'required'
        'resources.*.pricing.*.price_id' => 'nullable|integer|exists:pricing,price_id',
        'resources.*.pricing.*.customer_category_id' => 'nullable|integer|exists:customer_categories,id',
        'resources.*.pricing.*.unit_id' => 'required|integer|exists:units,unit_id',
        'resources.*.pricing.*.price' => 'required|numeric|min:0',
        // ------------------------------------
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
        // --- LOGIKA VALIDASI BARU DAN LEBIH KETAT ---
        // Hanya lakukan validasi jika outlet_ids dikirim.
        if (array_key_exists('outlet_ids', $this->payload)) {
            // Panggil method validasi dari DBRepo.
            // Method ini sekarang hanya memeriksa apakah outlet cocok dengan business_id produk yang ada.
            if (!DBRepo::validateOutletConsistency($this->payload['product_id'], $this->payload['outlet_ids'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, 'One or more outlets do not belong to the business unit of this product.')
                        ->setReportId('MPU2') // Manage Product Update 2
                );
            }
        }
        // ---------------------------------------------

        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $update = $dbRepo->updateData();

        if ($update->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $update->message]);
    }
}
