<?php

namespace App\Http\Controllers\REST\V1\Manage\Units;

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
        'unit_id' => 'required|integer|exists:units,unit_id',
        'name' => 'string|max:255',
        'description' => 'nullable|string',
        'type' => 'in:QUANTITY,TIME',
        'value_in_seconds' => 'required_if:type,TIME|integer|nullable'
    ];

    protected $privilegeRules = [
        "UNIT_MANAGE_VIEW",
        "UNIT_MANAGE_UPDATE",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Hanya lakukan validasi unik jika field 'name' dikirim oleh klien.
        if (array_key_exists('name', $this->payload)) {
            // Panggil DBRepo untuk melakukan pengecekan unik secara manual
            if (!DBRepo::isNameUniqueOnUpdate($this->payload['name'], $this->payload['unit_id'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, 'The unit name has already been taken.')
                        ->setReportId('MUU1') // Manage Unit Update 1
                );
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
