<?php

namespace App\Http\Controllers\REST\V1\Manage\Cooperation\Savings;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
{
    public function __construct(?array $payload = [], ?array $file = [], ?array $auth = [])
    {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'id' => 'required|integer|exists:members,id',
        'member_code' => 'string',
        'name' => 'string|max:100',
        'is_active' => 'nullable|boolean',
    ];

    protected $privilegeRules = [
        "MEMBER_MANAGE_VIEW",
        "MEMBER_MANAGE_UPDATE",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        if (array_key_exists('member_code', $this->payload)) {
            if (!DBRepo::isMemberCodeUniqueOnUpdate($this->payload['member_code'], $this->payload['id'])) {
                return $this->error((new Errors)->setMessage(409, 'The member code has already been taken.'));
            }
        }
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
