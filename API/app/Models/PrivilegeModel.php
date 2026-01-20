<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivilegeModel extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'privilege';
    protected $fillable = ['code', 'description'];
}
