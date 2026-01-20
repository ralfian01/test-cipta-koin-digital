<?php

namespace App\Http\Controllers\REST\V1\Manage\PaymentMethods;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Get extends BaseREST
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
     * @var array Property that contains the payload rules.
     * Contoh: /manage/payment-methods?type=EDC
     */
    protected $payloadRules = [
        'id' => 'nullable|integer|exists:payment_methods,id',
        'type' => 'nullable|string|in:CASH,EDC,QRIS,OTHER',
        'is_active' => 'nullable|boolean',
        'keyword' => 'nullable|string|min:2',
        'page' => 'nullable|integer|min:1',
        'per_page' => 'nullable|integer|min:1|max:100',
    ];

    protected $privilegeRules = [
        "PAYMENT_METHOD_VIEW",
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

        if (isset($this->payload['id'])) {
            return $this->error((new Errors)->setMessage(404, 'Payment method not found.'));
        }

        return $this->respond(200, null);
    }
}
