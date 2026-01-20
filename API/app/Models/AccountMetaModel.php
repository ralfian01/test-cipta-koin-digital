<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 *
 */
class AccountMetaModel extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'account__meta';
    protected $fillable = [
        'id',
        'code',
        'value',
    ];

    /**
     * Relation with table account
     */
    public function account()
    {
        return $this->belongsTo(AccountModel::class, 'id');
    }
}
