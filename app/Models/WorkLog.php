<?php
namespace App\Models;

use App\Enums\WorkLogStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkLog extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'project_id', 'log_date', 'start_time', 'end_time',
        'hours_spent', 'status', 'project_stage_snapshot', 'note', 'blocker',
    ];

    protected function casts(): array
    {
        return [
            'log_date'    => 'date',
            'hours_spent' => 'decimal:2',
            'status'      => WorkLogStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
