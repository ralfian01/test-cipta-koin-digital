<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Invoices;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Finance\Settings\AccountMapping\DBRepo as SettingsRepo;

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

    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'contact_id' => 'required|integer|exists:finance_contacts,id',
        'invoice_date' => 'required|date_format:Y-m-d',
        'due_date' => 'required|date_format:Y-m-d|after_or_equal:invoice_date',
        'items' => 'required|array|min:1',
        'items.*.account_chart_id' => 'required|integer|exists:account_charts,id',
        'items.*.description' => 'required|string',
        'items.*.amount' => 'required|numeric|min:0.01',
        'initial_payment_amount' => 'nullable|numeric|min:0',
        'payment_method_account_id' => 'required_with:initial_payment_amount|integer|exists:account_charts,id'
    ];


    protected $privilegeRules = [
        "MANAGE_FINANCE_INVOICE_VIEW",
        "MANAGE_FINANCE_INVOICE_INSERT",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Panggil static method dari SettingsRepo
        $isSet = SettingsRepo::isDefaultCashAccountSet($this->payload['business_id']);
        $isSetAR = SettingsRepo::isDefaultARAccountSet($this->payload['business_id']);

        if (!$isSet || !$isSetAR) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'Default cash account is not configured for this business unit. Please set it up in the finance settings.')
                    ->setReportId('MFII1') // Manage Cash Receipt Validation 1
            );
        }

        // Validasi 1: Pastikan kontak adalah CUSTOMER
        if (!DBRepo::isContactACustomer($this->payload['contact_id'])) {
            return $this->error((new Errors)->setMessage(409, 'The selected contact is not a customer.'));
        }

        // Validasi 2: Pastikan akun pendapatan yang dipilih adalah tipe REVENUE
        $itemAccountIds = array_column($this->payload['items'], 'account_chart_id');
        if (!DBRepo::areAccountsOfType($itemAccountIds, 'REVENUE')) {
            return $this->error((new Errors)->setMessage(409, 'One or more item accounts are not categorized as REVENUE.'));
        }

        // Validasi 3: Pastikan akun pembayaran awal adalah akun Kas/Bank
        if (isset($this->payload['payment_method_account_id'])) {
            if (!DBRepo::isAccountCashEquivalent($this->payload['payment_method_account_id'])) {
                return $this->error((new Errors)->setMessage(409, 'The selected payment account is not a Cash or Bank account.'));
            }
        }

        return $this->insert();
    }

    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $insert = $dbRepo->insertData();
        if ($insert->status) {
            return $this->respond(201, ['invoice_id' => $insert->data->invoice_id]);
        }
        return $this->error(500, ['reason' => $insert->message]);
    }
}
