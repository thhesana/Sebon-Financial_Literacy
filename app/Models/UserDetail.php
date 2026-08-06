<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'Users_Detail';
    protected $primaryKey = 'user_id';
    public $incrementing = true;
    public $timestamps = false; // table has created_at/updated_at but with non-standard update behavior; we set manually

    protected $fillable = [
        'username',
        'password_hash',
        'role',
        'active',
        'usertype_id',
        'failed_login_attempts',
        'last_failed_login',
        'last_login',
        'account_locked',
        'locked_until',
        'password_changed_at',
        'email',
        'is_password_expired',
        'updated_at',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_login' => 'datetime',
        'last_failed_login' => 'datetime',
        'locked_until' => 'datetime',
        'password_changed_at' => 'datetime',
        'account_locked' => 'boolean',
        'is_password_expired' => 'boolean',
    ];
}