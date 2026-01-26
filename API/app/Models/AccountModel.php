<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Hash;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @method static mixed getWithPrivileges() Get account with its privileges
 * @method mixed getWithPrivileges() Get account with its privileges
 */
class AccountModel extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'account';
    protected $fillable = [
        'uuid',
        'username',
        'password',
        'role_id',
        'status_active',
        'status_delete',
    ];
    protected $hidden = [
        'password'
    ];

    /**
     * Relation with table role
     */
    public function accountRole()
    {
        return $this->belongsTo(RoleModel::class, 'role_id');
    }

    /**
     * Privilege from relation between account, account__privilege and privilege tables
     */
    public function accountPrivilege()
    {
        return $this->belongsToMany(PrivilegeModel::class, 'account__privilege', 'account_id', 'privilege_id');
    }

    /**
     * Mutator untuk secara otomatis mengenkripsi (hash) password
     * setiap kali nilainya diatur.
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn($value) => Hash::make($value),
        );
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'username' => $this->username,
        ];
    }

    /**
     * Get account with its privileges
     */
    protected function scopeGetWithPrivileges(Builder $query)
    {
        return $query
            ->with(['accountPrivilege', 'accountRole.rolePrivilege'])
            ->addSelect(['id', 'role_id'])
            ->get()
            ->map(function ($acc) {

                // $acc->makeHidden(['accountPrivilege', 'roles']);
                $acc->makeHidden(['accountPrivilege']);

                if (isset($acc->accountPrivilege)) {
                    $acc->privileges = $acc->accountPrivilege->map(function ($prv) {
                        return $prv->code;
                    })->toArray();
                }

                if (isset($acc->accountRole)) {
                    $acc->privileges = $acc->accountRole->rolePrivilege->map(function ($prv) {
                        return $prv->code;
                    })->toArray();
                }

                $acc->privileges = array_unique($acc->privileges);
                return $acc;
            });
    }
}
