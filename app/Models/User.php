<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int id
 * @property string nickname
 * @property string email
 * @property string email_token
 * @property string email_verified_at
 * @property string password
 * @property string remember_token
 * @property string created_at
 * @property string updated_at
 *
 * @property UsersAdmin usersAdmin
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nickname',
        'email',
        'email_token',
        'password',
        'email_verified_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isAdmin(): bool
    {
        return !is_null($this->usersAdmin);
    }

    public function usersAdmin()
    {
        return $this->hasOne(UsersAdmin::class, 'user_id', 'id');
    }

    public function addUserAdmin(int $id): bool
    {
        DB::beginTransaction();

        try {
            UsersAdmin::create([
                'user_id' => $id
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;
    }

    public function deleteUserAdmin(int $id):bool
    {
        DB::beginTransaction();

        try {
            UsersAdmin::destroy([
                'user_id' => $id
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;
    }
}
