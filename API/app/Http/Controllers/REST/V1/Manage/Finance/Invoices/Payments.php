<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Invoices;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Payments extends BaseREST
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
        'id' => 'required|integer|exists:invoices,id',
        'payment_date' => 'required|date_format:Y-m-d',
        'amount' => 'required|numeric|min:0.01',
        'payment_method_account_id' => 'required|integer|exists:account_charts,id',
        'notes' => 'nullable|string',
    ];

    protected $privilegeRules = [
        "MANAGE_FINANCE_INVOICE_VIEW",
        "MANAGE_FINANCE_INVOICE_UPDATE",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    /**
     * nextValidation sekarang memanggil static method dari DBRepo.
     */
    private function nextValidation()
    {
        // Validasi 1: Panggil method validasi pembayaran dari DBRepo
        list($isValid, $message) = DBRepo::validatePayment(
            $this->payload['id'],
            (float)$this->payload['amount']
        );

        if (!$isValid) {
            // Gunakan pesan error dinamis dari validator
            return $this->error((new Errors)
                ->setMessage(409, $message)
                ->setReportId('MFIP1'));
        }

        // Validasi 2: Pastikan akun pembayaran adalah akun Kas/Bank
        if (!DBRepo::isAccountCashEquivalent($this->payload['payment_method_account_id'])) {
            return $this->error((new Errors)->setMessage(409, 'The selected payment account is not a Cash or Bank account.'));
        }

        return $this->insert();
    }

    public function insert()
    {
        // Kita perlu mengambil objek Invoice di sini untuk diteruskan ke DBRepo
        $invoice = \App\Models\Invoice::find($this->payload['id']);

        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $insert = $dbRepo->insertPayment($invoice, $this->payload);

        if ($insert->status) {
            return $this->respond(201);
        }
        return $this->error(500, ['reason' => $insert->message]);
    }
}
