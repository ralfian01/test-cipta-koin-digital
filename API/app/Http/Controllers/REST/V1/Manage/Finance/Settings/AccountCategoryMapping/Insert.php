<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Settings\AccountCategoryMapping;

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
     * --- CONTOH PAYLOAD ---
     * {
     *     "business_id": 1,
     *     "default_cash_account_id": 3
     * }
     */
    protected $payloadRules = [
        'business_id' => 'required|integer|exists:business,id',
        'mappings' => 'required|array|min:1',
        'mappings.*.source_category_id' => 'required|integer|exists:account_chart_categories,id',
        'mappings.*.recommended_category_id' => 'required|integer|exists:account_chart_categories,id',
    ];

    protected $privilegeRules = [
        "FINANCE_SETTING_VIEW",
        "FINANCE_SETTING_UPDATE"
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Panggil static method dari DBRepo untuk mencari duplikat
        $existingMappings = DBRepo::findExistingMappings(
            $this->payload['business_id'],
            $this->payload['mappings']
        );

        // Jika ada duplikat yang ditemukan
        if ($existingMappings->isNotEmpty()) {
            // Format pesan error agar lebih informatif
            $errorMessages = $existingMappings->map(function ($mapping) {
                return sprintf(
                    "Rule '%s -> %s' already exists.",
                    $mapping->sourceCategory->name,
                    $mapping->recommendedCategory->name
                );
            });

            return $this->error(
                (new Errors)
                    ->setMessage(409, $errorMessages->implode(' '))
                    ->setReportId('MFSACMI1')
            );
        }

        return $this->insert();
    }

    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->insertData();
        if ($result->status) {
            return $this->respond(201);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
