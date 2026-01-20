<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePrivilegeModel extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'role__privilege';
    protected $fillable = ['role_id', 'privilege_id'];
}
