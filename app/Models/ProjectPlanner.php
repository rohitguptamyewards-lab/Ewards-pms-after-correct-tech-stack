<?php
namespace App\Models;

use App\Enums\PlannerStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPlanner extends Model
{
    use Auditable;

    protected $fillable = [
        'project_id', 'title', 'description', 'milestone_flag',
        'assigned_to', 'due_date', 'status', 'order_index',
    ];

    protected function casts(): array
    {
        return [
            'status'         => PlannerStatus::class,
            'milestone_flag' => 'boolean',
            'due_date'       => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'assigned_to');
    }
}
