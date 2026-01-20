<?php

namespace App\Http\Controllers\REST\V1\My\Todo;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
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
        'id' => 'required|integer|exists:todos,id',
        'title' => 'string|max:250',
        'description' => 'nullable|string',
        'is_done' => 'nullable|boolean'
    ];

    protected $privilegeRules = [
        "TODO_VIEW",
        "TODO_UPDATE",
    ];

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        return $this->update();
    }

    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $update = $dbRepo->updateData();

        if ($update->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $update->message]);
    }
}
