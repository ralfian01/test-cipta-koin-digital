<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\ChartOfAccounts;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
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
        'id' => 'required|integer|exists:account_charts,id',
        'parent_id' => 'nullable|integer|exists:account_charts,id',
        'account_chart_category_id' => 'nullable|integer|exists:account_chart_categories,id',
        'account_code' => 'string|max:50',
        'account_name' => 'string|max:100',
    ];

    protected $privilegeRules = [
        "ACCOUNT_CHART_MANAGE_VIEW",
        "ACCOUNT_CHART_MANAGE_UPDATE",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Validasi unik untuk account_code
        if (isset($this->payload['account_code'])) {
            $isUnique = DBRepo::isCodeUniqueOnUpdate($this->payload['account_code'], $this->payload['id']);
            if (!$isUnique) {
                return $this->error((new Errors)->setMessage(409, 'The account code has already been taken.'));
            }
        }

        // Validasi untuk account_chart_category_id
        if (isset($this->payload['parent_id'], $this->payload['account_chart_category_id'])) {
            if (!DBRepo::isChildInSameCategory($this->payload['parent_id'], $this->payload['account_chart_category_id'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, 'The child category id must be the same as parent category id.')
                        ->setReportId('MFCI2')
                );
            }
        }
        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->updateData();
        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
