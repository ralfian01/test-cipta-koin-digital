<?php

namespace App\Http\Middleware;

use App\Models\AccountModel;
use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddleware
{
    protected $validAuthType = [
        'key',
        'basic',
        'bearer',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $authType = 'basic'): Response
    {
        if (!in_array($authType, $this->validAuthType)) {
            new Exception('Invalid authentication type');
        }

        return $this->{$authType}($request, $next);
    }

    /**
     * Client auth using X-API-KEY
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    private function key(Request $request, Closure $next): Response
    {
        // Key Authentication
        $apiKey = $request->header('X-API-KEY');

        return $next($request);
    }

    /**
     * Client auth using Basic Authorization
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    private function basic(Request $request, Closure $next): Response
    {
        // Collect header: Authorization
        $authHead = $request->header('Authorization');
        $authStatus = false;

        // Make sure authorization type is Basic
        if ($authHead && strpos($authHead, 'Basic ') === 0) {

            // Authorize client using Basic
            $authData = explode(':', base64_decode(str_replace('Basic ', '', $authHead)));

            // Validate username & password from model
            $accData =
                AccountModel::select(
                    'id as account_id',
                    'uuid as uuid',
                    'username as username',
                    'status_delete as status_delete',
                    'status_active as status_active'
                )
                ->where('username', '=', $authData[0])
                ->where('password', '=', hash('SHA256', $authData[1]))
                ->getWithPrivileges();

            if (!$accData->isEmpty()) {

                $accData = $accData[0];

                // Make sure account not deleted and not suspended
                if (
                    !$accData->status_delete
                    && $accData->status_active
                ) {
                    $authStatus = true;

                    // Remove unnecessary columns
                    unset($accData->status_delete);
                    unset($accData->status_active);

                    // Set auth data
                    $request->attributes->set('auth_data', $accData->toArray());
                }
            }
        }

        $request->attributes->set('auth_status', $authStatus);

        return $next($request);
    }

    /**
     * Client auth using Bearer Authorization
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    private function bearer(Request $request, Closure $next): Response
    {
        // Collect header: Authorization
        $authHead = $request->header('Authorization');
        $authStatus = false;

        // Make sure authorization type is Bearer
        if ($authHead && strpos($authHead, 'Bearer ') === 0) {

            // Authorize client using JWT
            $token = str_replace('Bearer ', '', $authHead);

            try {
                $jwtObject = JWT::decode($token, new Key(env('JWT_SECRET_KEY'), 'HS256'));

                $uuid = base64_decode($jwtObject->uid_b64);

                // Validate uuid from model
                $accData =
                    AccountModel::select(
                        'id as account_id',
                        'uuid as uuid',
                        'username as username',
                        'status_delete as status_delete',
                        'status_active as status_active'
                    )
                    ->where('uuid', $uuid)
                    ->getWithPrivileges();

                if (!$accData->isEmpty()) {

                    $accData = $accData[0];

                    // Make sure account not deleted and not suspended
                    if (
                        !$accData->status_delete
                        && $accData->status_active
                    ) {
                        $authStatus = true;

                        // Remove unnecessary columns
                        unset($accData->status_delete);
                        unset($accData->status_active);

                        // Set auth data
                        $request->attributes->set('auth_data', $accData->toArray());
                    }
                }
            } catch (\Exception $e) {
                // 
            }
        }

        $request->attributes->set('auth_status', $authStatus);

        return $next($request);
    }
}
