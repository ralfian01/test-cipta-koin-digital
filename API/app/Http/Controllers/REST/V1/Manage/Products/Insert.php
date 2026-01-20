<?php

namespace App\Http\Controllers\REST\V1\Manage\Products;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Products\DBRepo;

class Insert extends BaseREST
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
     * --- CONTOH PAYLOAD KONSUMSI DENGAN PRICING ---
     * {
     *     "business_id": 1, "category_id": 2, "name": "Kaos Polo", "product_type": "CONSUMPTION",
     *     "variants": [
     *         {
     *             "name": "Biru, L", "stock_quantity": 100,
     *             "pricing": [
     *                 { "customer_category_id": 1, "price": 250000 },
     *                 { "customer_category_id": 2, "price": 225000 }
     *             ]
     *         }
     *     ]
     * }
     *
     * --- CONTOH PAYLOAD SEWA DENGAN PRICING ---
     * {
     *     "business_id": 1, "category_id": 1, "name": "Sewa Lapangan Badminton", "product_type": "RENTAL",
     *     "resources": [
     *         {
     *             "name": "Lapangan A",
     *             "availability": [ { "day_of_week": 1, "start_time": "08:00", "end_time": "22:00" } ],
     *             "pricing": [
     *                 { "customer_category_id": 1, "unit_id": 2, "price": 85000 },
     *                 { "customer_category_id": 2, "unit_id": 2, "price": 75000 }
     *             ]
     *         }
     *     ]
     * }
     */
    protected $payloadRules = [
        // Informasi Dasar Produk
        'business_id' => 'nullable|integer|exists:business,id|required_without:outlet_ids',
        // outlet_ids sekarang opsional
        'outlet_ids' => 'nullable|array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
        'category_id' => 'nullable|integer|exists:product_categories,id',
        'name' => 'required|string|max:255',
        'product_type' => 'required|string|in:CONSUMPTION,RENTAL',

        // Aturan untuk Produk KONSUMSI
        'variants' => 'required_if:product_type,CONSUMPTION|array|min:1',
        'variants.*.name' => 'required_with:variants|string|max:255',
        'variants.*.stock_quantity' => 'nullable|integer|min:0',
        'variants.*.pricing' => 'required_with:variants|array|min:1',
        'variants.*.pricing.*.customer_category_id' => 'nullable|integer|exists:customer_categories,id',
        'variants.*.pricing.*.price' => 'required_with:variants.*.pricing|numeric|min:0',

        // Aturan untuk Produk SEWA
        'resources' => 'required_if:product_type,RENTAL|array|min:1',
        'resources.*.name' => 'required_with:resources|string|max:255',
        'resources.*.availability' => 'required_with:resources|array',
        'resources.*.availability.*.day_of_week' => 'required_with:resources.*.availability|integer|between:0,6',
        'resources.*.availability.*.start_time' => 'required_with:resources.*.availability|date_format:H:i',
        'resources.*.availability.*.end_time' => 'required_with:resources.*.availability|date_format:H:i|after:resources.*.availability.*.start_time',
        'resources.*.pricing' => 'required_with:resources|array|min:1',
        'resources.*.pricing.*.customer_category_id' => 'nullable|integer|exists:customer_categories,id',
        'resources.*.pricing.*.unit_id' => 'required_with:resources.*.pricing|integer|exists:units,unit_id',
        'resources.*.pricing.*.price' => 'required_with:resources.*.pricing|numeric|min:0',
    ];

    protected $privilegeRules = [
        "PRODUCT_MANAGE_VIEW",
        "PRODUCT_MANAGE_INSERT",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Panggil method validasi dari DBRepo
        $validationResult = DBRepo::validateAndDetermineBusinessId($this->payload);

        if (!$validationResult->status) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, $validationResult->message)
                    ->setReportId('MPI3') // Manage Product Insert 3
            );
        }

        // --- KUNCI LOGIKA ---
        // Timpa atau isi $this->payload['business_id'] dengan hasil validasi
        $this->payload['business_id'] = $validationResult->business_id;
        // --------------------

        return $this->insert();
    }


    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $insert = $dbRepo->insertData();
        if ($insert->status) {
            return $this->respond(201, ['product_id' => $insert->data->product_id]);
        }
        return $this->error(500, ['reason' => $insert->message]);
    }
}
