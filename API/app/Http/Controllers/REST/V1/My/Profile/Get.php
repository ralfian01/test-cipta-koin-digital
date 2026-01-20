<?php

namespace App\Http\Controllers\REST\V1\My\Profile;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\My\Profile\DBRepo;

class Get extends BaseREST
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

    // Endpoint ini tidak memerlukan payload dari klien
    protected $payloadRules = [];
    protected $privilegeRules = []; // Mungkin tidak perlu privilege, karena ini untuk diri sendiri

    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Pastikan data otentikasi ada
        if (empty($this->payload['account_id'])) {
            return $this->error((new Errors)->setMessage(401, 'Authentication required.'));
        }
        return $this->get();
    }

    public function get()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $result = $dbRepo->getProfile();

        if ($result->status) {
            return $this->respond(200, $result->data);
        }
        return $this->error((new Errors)->setMessage(404, 'Profile not found for the authenticated user.'));
    }
}
