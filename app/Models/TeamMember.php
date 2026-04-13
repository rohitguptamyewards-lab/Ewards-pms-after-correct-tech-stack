<?php
namespace App\Models;

use App\Enums\Role;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TeamMember extends Authenticatable
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'team_members';

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role'      => Role::class,
            'is_active' => 'boolean',
            'password'  => 'hashed',
        ];
    }
}
