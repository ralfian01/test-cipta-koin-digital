<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory\Items;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Delete extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'id' => 'required|integer|exists:inventory_items,id'
    ];

    protected $privilegeRules = [
        "INVENTORY_MANAGE_VIEW",
        "INVENTORY_MANAGE_DELETE",
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
