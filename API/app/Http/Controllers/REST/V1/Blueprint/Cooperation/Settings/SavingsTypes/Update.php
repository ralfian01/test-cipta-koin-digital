<?php

namespace App\Http\Controllers\REST\V1\Blueprint\Cooperation\Settings\SavingsTypes;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;

class Update extends BaseREST
{
    public function __construct(?array $p = [], ?array $f = [], ?array $a = [])
    {
        $this->payload = $p;
        $this->file = $f;
        $this->auth = $a;
        return $this;
    }

    /**
     * @var array
     * 'unique' check dipindahkan ke nextValidation
     */
    protected $payloadRules = [
        'id' => 'required|integer|exists:cooperation_savings_types,id',
        'name' => 'string|max:255',
        'code' => 'string|max:50|alpha_dash',
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
        // Validasi keunikan untuk 'name' jika ada di payload
        if (isset($this->payload['name'])) {
            if (!DBRepo::isFieldUniqueOnUpdate('name', $this->payload['name'], $this->payload['id'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, "The name '{$this->payload['name']}' has already been taken.")
                        ->setReportId('MCSSTU1')
                );
            }
        }

        // Validasi keunikan untuk 'code' jika ada di payload
        if (isset($this->payload['code'])) {
            if (!DBRepo::isFieldUniqueOnUpdate('code', $this->payload['code'], $this->payload['id'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, "The code '{$this->payload['code']}' has already been taken.")
                        ->setReportId('MCSSTU2')
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
