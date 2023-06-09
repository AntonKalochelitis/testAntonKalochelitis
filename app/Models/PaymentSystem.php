<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer id
 * @property string name
 * @property string setting
 * @property boolean active
 * @property string created_at
 * @property string updated_at
 * @property string deleted_at
 */
class PaymentSystem extends Model
{
    public const ACTIVE = 1;
    public const NOACTIVE = 0;

    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'setting',
        'active',
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
