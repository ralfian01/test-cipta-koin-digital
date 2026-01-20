<?php

namespace App\Http\Controllers\REST\V1\Manage\Packages;

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
        'id' => 'required|integer|exists:packages,id'
    ];
    protected $privilegeRules = [
        "PACKAGE_MANAGE_VIEW",
        "PACKAGE_MANAGE_DELETE",
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
        $r = $dbRepo->deleteData();
        if ($r->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $r->message]);
    }
}
