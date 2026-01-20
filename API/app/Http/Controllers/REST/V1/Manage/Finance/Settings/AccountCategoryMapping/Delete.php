<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Settings\AccountCategoryMapping;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Delete extends BaseREST
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
     */
    protected $payloadRules = [
        'id' => 'required|integer|exists:account_category_mappings,id'
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
        return $this->delete();
    }

    public function delete()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->deleteData();

        if ($result->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $result->message]);
    }
}
