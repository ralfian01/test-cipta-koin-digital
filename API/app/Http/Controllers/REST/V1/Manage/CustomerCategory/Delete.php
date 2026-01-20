<?php

namespace App\Http\Controllers\REST\V1\Manage\CustomerCategory;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Delete extends BaseREST
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

    protected $payloadRules = [
        'id' => 'required|integer|exists:customer_categories,id',
    ];

    protected $privilegeRules = [
        "CUSTOMER_CATEGORY_MANAGE_VIEW",
        "CUSTOMER_CATEGORY_MANAGE_DELETE",
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
        $delete = $dbRepo->deleteData();

        if ($delete->status) {
            return $this->respond(200);
        }

        return $this->error(500, ['reason' => $delete->message]);
    }
}
