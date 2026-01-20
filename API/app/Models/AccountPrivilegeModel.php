<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountPrivilegeModel extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'account__privilege';
    protected $fillable = ['account_id', 'privilege_id'];
}
