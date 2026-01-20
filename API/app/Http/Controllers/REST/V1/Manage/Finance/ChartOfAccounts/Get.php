<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\ChartOfAccounts;

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

    // Endpoint ini tidak memerlukan payload, karena akan mengambil seluruh struktur pohon
    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'account_chart_category_ids' => 'nullable|array',
        'account_chart_category_ids.*' => 'required_with:include_no_category|integer|exists:account_chart_categories,id',
        'include_no_category' => 'nullable|boolean',
        'from' => 'nullable|in:ROOT,CHILD',
        'account_type' => 'nullable|in:HEAD,POST',
        'pair_recommendation_for_account_id' => 'nullable|integer|exists:account_charts,id',
    ];
    protected $privilegeRules = [
        "ACCOUNT_CHART_MANAGE_VIEW",
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
        return $this->error(500, ['reason' => $result->message]);
    }
}
