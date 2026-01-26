<?php

namespace App\Http\Middleware;

use App\Models\AccountModel;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;


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
        $authStatus = false;

        try {
            // Validasi JWT + resolve user
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                abort(401, 'Unauthenticated');
            }

            // Validasi status akun
            if ($user->status_delete || !$user->status_active) {
                abort(403, 'Account inactive');
            }

            // Load privilege (sesuai pola lama kamu)
            $accData =
                AccountModel::select(
                    'id as account_id',
                    'uuid',
                    'username'
                )
                ->where('id', $user->id)
                ->getWithPrivileges()
                ->first();

            if (!$accData) {
                abort(401, 'Account not found');
            }

            // Set auth status
            $authStatus = true;

            // Inject auth data ke request (minimal & bersih)
            $request->attributes->set('auth_data', [
                'account_id' => $accData->account_id,
                'uuid'       => $accData->uuid,
                'username'   => $accData->username,
                'privileges' => $accData->privileges ?? [],
            ]);
        } catch (TokenExpiredException $e) {
            abort(401, 'Token expired');
        } catch (TokenInvalidException $e) {
            abort(401, 'Token invalid');
        } catch (JWTException $e) {
            abort(401, 'Token missing');
        }

        $request->attributes->set('auth_status', $authStatus);

        return $next($request);
    }
}
