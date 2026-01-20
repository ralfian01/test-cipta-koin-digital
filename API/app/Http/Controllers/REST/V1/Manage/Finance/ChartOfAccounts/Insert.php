<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\ChartOfAccounts;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Finance\ChartOfAccounts\DBRepo;

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
     * @var array Property that contains the payload rules.
     */
    protected $payloadRules = [
        // parent_id bersifat opsional. Jika null, ini adalah akun level atas.
        'parent_id' => 'nullable|integer|exists:account_charts,id',
        'business_id' => 'required|integer|exists:business,id',
        'account_chart_category_id' => 'nullable|integer|exists:account_chart_categories,id',

        'account_code' => 'required|string|max:50',
        'account_name' => 'required|string|max:100',
    ];

    protected $privilegeRules = [
        "ACCOUNT_CHART_MANAGE_VIEW",
        "ACCOUNT_CHART_MANAGE_INSERT",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        if (!DBRepo::isCodeUniqueInBusiness($this->payload['account_code'], $this->payload['business_id'])) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, 'The account code has already been taken for this business.')
                    ->setReportId('MFCI1')
            );
        }

        if (isset($this->payload['parent_id'], $this->payload['account_chart_category_id'])) {
            if (!DBRepo::isChildInSameCategory($this->payload['parent_id'], $this->payload['account_chart_category_id'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, 'The child category id must be the same as parent category id.')
                        ->setReportId('MFCI2')
                );
            }
        }

        return $this->insert();
    }

    /** 
     * Function to insert data 
     * @return object
     */
    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $insert = $dbRepo->insertData();

        if ($insert->status) {
            return $this->respond(201, ['id' => $insert->data->id]);
        }

        return $this->error(500, ['reason' => $insert->message]);
    }
}
