<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static mixed getWithPrivileges() Get role with its privileges
 * @method mixed getWithPrivileges() Get role with its privileges
 */
class RoleModel extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'role';
    protected $fillable = ['code', 'name'];

    /**
     * Privilege from relation between role, role__privilege, and privilege tables
     */
    public function rolePrivilege()
    {
        return $this->belongsToMany(PrivilegeModel::class, 'role__privilege', 'role_id', 'privilege_id');
    }

    /**
     * Get role with its privileges
     */
    protected function scopeGetWithPrivileges(Builder $query)
    {
        return $query
            ->with(['rolePrivilege'])
            ->addSelect(['id'])
            ->get()
            ->map(function ($role) {

                $role->makeHidden(['rolePrivilege']);

                if (!is_null($role->rolePrivilege)) {
                    $role->privileges = $role->rolePrivilege->map(function ($privilege) {
                        return $privilege->tp_code;
                    })->toArray();
                }

                return $role;
            });
    }
}
