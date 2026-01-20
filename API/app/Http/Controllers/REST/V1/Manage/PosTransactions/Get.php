<?php

namespace App\Http\Controllers\REST\V1\Manage\PosTransactions;

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

    /**
     * @var array
     * Contoh: /manage/pos-transactions?business_id=1&start_date=2025-09-01&end_date=2025-09-30
     */
    protected $payloadRules = [
        'id' => 'nullable|integer|exists:pos_transactions,id', // Untuk mengambil 1 transaksi spesifik
        'business_id' => 'required|integer|exists:business,id', // Wajib untuk scope data
        'outlet_id' => 'nullable|integer|exists:outlets,id',
        'employee_id' => 'nullable|integer|exists:employees,id',
        'customer_id' => 'nullable|integer|exists:customers,id',
        'start_date' => 'nullable|date_format:Y-m-d',
        'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        'keyword' => 'nullable|string|min:2', // Untuk mencari nama customer atau ID transaksi
        'page' => 'nullable|integer|min:1',
        'per_page' => 'nullable|integer|min:1|max:100',
    ];

    protected $privilegeRules = [];
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
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->getData();

        if ($result->status) {
            return $this->respond(200, $result->data);
        }

        if (isset($this->payload['id'])) {
            return $this->error((new Errors)->setMessage(404, 'Transaction not found.'));
        }

        return $this->respond(200, null);
    }
}
