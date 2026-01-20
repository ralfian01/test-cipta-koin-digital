<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Bills;

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
     * --- CONTOH PENGGUNAAN ---
     * 1. Daftar: GET /.../invoices?business_id=1&status=PARTIALLY_PAID
     * 2. Detail:  GET /.../invoices?id=1
     */
    protected $payloadRules = [
        // Filter Wajib/Opsional
        'id' => 'nullable|integer|exists:bills,id',
        'business_id' => 'required_without:id|integer|exists:business,id', // Wajib jika bukan ambil detail
        'contact_id' => 'nullable|integer|exists:finance_contacts,id',
        'status' => 'nullable|string|in:DRAFT,SENT,PARTIALLY_PAID,PAID,VOID',
        'start_date' => 'nullable|date_format:Y-m-d',
        'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        'keyword' => 'nullable|string|min:2',

        // Paginasi
        'page' => 'nullable|integer|min:1',
        'per_page' => 'nullable|integer|min:1|max:100',
    ];

    protected $privilegeRules = [
        "MANAGE_FINANCE_BILL_VIEW",
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
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->getData();

        if ($result->status) {
            return $this->respond(200, $result->data);
        }

        // Jika mencari by ID dan tidak ketemu
        if (isset($this->payload['id'])) {
            return $this->error((new Errors)->setMessage(404, 'Invoice not found.'));
        }

        // Jika daftar kosong
        return $this->respond(200, null);
    }
}
