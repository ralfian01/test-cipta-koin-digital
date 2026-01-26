<?php

namespace App\Http\Controllers\REST\V1\Auth;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Models\AccountMetaModel;
use App\Models\AccountModel;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class Account extends BaseREST
{
    public function __construct(
        ?array $payload = [],
        ?array $file = [],
        ?array $auth = []
    ) {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
    }

    /**
     * @var array Property that contains the payload rules
     */
    protected $payloadRules = [];

    /**
     * @var array Property that contains the privilege data
     */
    protected $privilegeRules = [];


    /**
     * The method that starts the main activity
     * @return null
     */
    protected function mainActivity($id = null)
    {
        return $this->nextValidation();
    }

    /**
     * Handle the next step of payload validation
     * @return void
     */
    private function nextValidation()
    {
        $authorize = $this->authorize();

        if (!$authorize->status) {
            return $this->error(
                (new Errors)
                    ->setStatus($authorize->errorCode, $authorize->errorStatus)
                    ->setMessage($authorize->errorMessage)
            );
        }

        $this->auth = $authorize->auth;

        return $this->get();
    }

    /** 
     * Function to get data 
     * @return object
     */
    public function get()
    {
        try {

            // Generate JWT dari user yang sudah tervalidasi
            $token = JWTAuth::claims([
                'uuid' => $this->auth['uuid'],
            ])->fromUser($this->auth);

            return $this->respond(200, [
                'token' => $token
            ]);
        } catch (JWTException $e) {
            return $this->error(
                (new Errors)
                    ->setStatus(500, 'TOKEN_GENERATION_FAILED')
                    ->setMessage('Failed to generate token')
                    ->setDetail([
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                    ])
            );
        }
    }

    /**
     * @return object|bool
     */
    private function authorize()
    {
        $authorizeHead = $this->request->header('Authorization');

        if (!$authorizeHead || !str_starts_with($authorizeHead, 'Basic ')) {
            return $this->unauthorized();
        }

        $decoded = base64_decode(substr($authorizeHead, 6));
        if (!$decoded || !str_contains($decoded, ':')) {
            return $this->unauthorized();
        }

        [$username, $password] = explode(':', $decoded, 2);

        $accData = AccountModel::where('username', $username)->first();

        if (
            !$accData ||
            !Hash::check($password, $accData->password) ||
            $accData->status_delete ||
            !$accData->status_active
        ) {
            return $this->unauthorized();
        }

        return (object) [
            'status' => true,
            'auth'   => $accData
        ];
    }

    private function unauthorized()
    {
        return (object) [
            'status' => false,
            'errorCode' => 401,
            'errorStatus' => 'UNAUTHORIZED',
            'errorMessage' => 'Invalid credentials'
        ];
    }
}
