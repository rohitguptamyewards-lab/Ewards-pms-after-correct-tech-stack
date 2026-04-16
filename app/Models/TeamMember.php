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
        'name', 'email', 'password', 'role', 'employee_type', 'manager_id', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role'          => Role::class,
            'manager_id'    => 'integer',
            'is_active'     => 'boolean',
            'password'      => 'hashed',
        ];
    }

    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function reportees()
    {
        return $this->hasMany(self::class, 'manager_id');
    }
}
