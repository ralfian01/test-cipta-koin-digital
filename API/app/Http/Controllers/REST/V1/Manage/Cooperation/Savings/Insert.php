<?php

namespace App\Http\Controllers\REST\V1\Manage\Cooperation\Savings;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

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
     * --- CONTOH PAYLOAD (SANGAT SEDERHANA) ---
     * {
     *     "member_id": 101,
     *     "transaction_date": "2025-10-31",
     *     "description": "Setoran Simpanan Wajib & Sukarela",
     *     "transaction_type": "DEPOSIT",
     *     "items": [
     *         { "cooperation_savings_type_id": 2, "amount": 100000 },
     *         { "cooperation_savings_type_id": 3, "amount": 500000 }
     *     ]
     * }
     */
    protected $payloadRules = [
        'member_id' => 'required|integer|exists:members,id',
        'transaction_date' => 'required|date_format:Y-m-d',
        'description' => 'nullable|string',
        'transaction_type' => 'required|string|in:DEPOSIT,WITHDRAWAL',
        'items' => 'required|array|min:1',
        'items.*.cooperation_savings_type_id' => 'required|integer|exists:cooperation_savings_types,id',
        'items.*.amount' => 'required|numeric|min:0.01',
    ];

    protected $privilegeRules = [];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Validasi: Pastikan semua jenis simpanan yang dikirim memiliki mapping yang valid
        $savingsTypeIds = array_column($this->payload['items'], 'cooperation_savings_type_id');
        if (!DBRepo::areSavingsTypesMapped($savingsTypeIds)) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'One or more savings types do not have a valid account mapping configured in the settings.')
                    ->setReportId('MCSI1')
            );
        }
        return $this->insert();
    }

    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->insertTransaction();
        if ($result->status) {
            return $this->respond(201, [
                'journals_created' => $result->data->journals_created
            ]);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
