<?php
namespace App\Policies;

use App\Models\Project;
use App\Models\TeamMember;

class ProjectPolicy
{
    public function viewAny(TeamMember $user): bool
    {
        return true;
    }

    public function create(TeamMember $user): bool
    {
        return ($user->role->value ?? $user->role) === 'manager';
    }

    public function update(TeamMember $user, Project $project): bool
    {
        $role = $user->role->value ?? $user->role;
        return $role === 'manager' || $user->id === $project->owner_id;
    }

    public function delete(TeamMember $user, Project $project): bool
    {
        return ($user->role->value ?? $user->role) === 'manager';
    }
}
