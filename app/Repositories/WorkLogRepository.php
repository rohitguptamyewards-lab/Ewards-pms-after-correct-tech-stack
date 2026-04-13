<?php
namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class WorkLogRepository
{
    private const WORKLOG_CUSTOM_TASK_TYPE = 'worklog_custom_project';

    public function findAll(array $filters = [], int $perPage = 25)
    {
        $query = DB::table('work_logs')
            ->join('team_members', 'work_logs.user_id', '=', 'team_members.id')
            ->join('projects', 'work_logs.project_id', '=', 'projects.id')
            ->select(
                'work_logs.*',
                'team_members.name as user_name',
                'team_members.email as user_email',
                'projects.name as project_name'
            )
            ->whereNull('work_logs.deleted_at');

        if (!empty($filters['user_id'])) {
            $query->where('work_logs.user_id', $filters['user_id']);
        }
        if (!empty($filters['project_id'])) {
            $query->where('work_logs.project_id', $filters['project_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('work_logs.log_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('work_logs.log_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['status'])) {
            $query->where('work_logs.status', $filters['status']);
        }

        return $query->orderByDesc('work_logs.log_date')
            ->orderByDesc('work_logs.start_time')
            ->paginate($perPage);
    }

    public function findByUser(int $userId, array $filters = [], int $perPage = 25)
    {
        $filters['user_id'] = $userId;
        return $this->findAll($filters, $perPage);
    }

    public function findById(int $id): ?stdClass
    {
        return DB::table('work_logs')
            ->join('team_members', 'work_logs.user_id', '=', 'team_members.id')
            ->join('projects', 'work_logs.project_id', '=', 'projects.id')
            ->select(
                'work_logs.*',
                'team_members.name as user_name',
                'projects.name as project_name'
            )
            ->where('work_logs.id', $id)
            ->whereNull('work_logs.deleted_at')
            ->first();
    }

    public function create(array $data): int
    {
        // Auto-calculate hours_spent from start/end time
        if (!empty($data['start_time']) && !empty($data['end_time'])) {
            $start = strtotime($data['start_time']);
            $end = strtotime($data['end_time']);
            if ($end > $start) {
                $data['hours_spent'] = round(($end - $start) / 3600, 2);
            }
        }

        if (!empty($data['project_id'])) {
            $data['project_stage_snapshot'] = $this->resolveProjectStageSnapshot((int) $data['project_id']);
        }

        $data['created_at'] = now();
        $data['updated_at'] = now();
        return DB::table('work_logs')->insertGetId($data);
    }

    public function update(int $id, array $data): bool
    {
        // Auto-calculate hours_spent using effective start/end (incoming + existing)
        $existing = DB::table('work_logs')
            ->select('project_id', 'project_stage_snapshot', 'start_time', 'end_time')
            ->where('id', $id)
            ->first();

        if ($existing) {
            $effectiveStart = $data['start_time'] ?? $existing->start_time;
            $effectiveEnd = $data['end_time'] ?? $existing->end_time;

            if (!empty($effectiveStart) && !empty($effectiveEnd)) {
                $start = strtotime($effectiveStart);
                $end = strtotime($effectiveEnd);
                if ($end > $start) {
                    $data['hours_spent'] = round(($end - $start) / 3600, 2);
                }
            }

            if (array_key_exists('project_id', $data)) {
                $data['project_stage_snapshot'] = $this->resolveProjectStageSnapshot((int) $data['project_id']);
            } elseif (empty($existing->project_stage_snapshot) && !empty($existing->project_id)) {
                $data['project_stage_snapshot'] = $this->resolveProjectStageSnapshot((int) $existing->project_id);
            }
        }

        $data['updated_at'] = now();
        return DB::table('work_logs')->where('id', $id)->update($data) > 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('work_logs')->where('id', $id)->update([
            'deleted_at' => now(),
        ]) > 0;
    }

    public function sumHoursForUser(int $userId, string $dateFrom, string $dateTo): float
    {
        return (float) DB::table('work_logs')
            ->where('user_id', $userId)
            ->whereBetween('log_date', [$dateFrom, $dateTo])
            ->whereNull('deleted_at')
            ->sum('hours_spent');
    }

    public function getLastEndTime(int $userId, string $date): ?string
    {
        return DB::table('work_logs')
            ->where('user_id', $userId)
            ->where('log_date', $date)
            ->whereNull('deleted_at')
            ->orderByDesc('end_time')
            ->value('end_time');
    }

    private function resolveProjectStageSnapshot(int $projectId): ?string
    {
        $project = DB::table('projects')
            ->select('status', 'custom_task_type')
            ->where('id', $projectId)
            ->whereNull('deleted_at')
            ->first();

        if (!$project || $project->custom_task_type === self::WORKLOG_CUSTOM_TASK_TYPE) {
            return null;
        }

        $latestStage = DB::table('project_stages')
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('stage_name');

        return $latestStage ?: $project->status;
    }
}
