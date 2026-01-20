<?php

namespace App\Http\Controllers\REST\V1\Manage\ProductCategories;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\ProductCategories\DBRepo;

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
        'name' => 'required|string|max:100',
    ];

    protected $privilegeRules = [
        "PRODUCT_CATEGORY_MANAGE_VIEW",
        "PRODUCT_CATEGORY_MANAGE_INSERT",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        if (!DBRepo::isNameUniqueInBusiness($this->payload['name'], $this->payload['business_id'])) {
            return $this->error((new Errors)->setMessage(409, 'The category name has already been taken for this business.'));
        }
        return $this->insert();
    }

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
