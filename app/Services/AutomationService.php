<?php
namespace App\Services;

use App\Mail\AutomationEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AutomationService
{
    /**
     * Process all active scheduled automations.
     * Called by the artisan command on a schedule.
     */
    public function processScheduledAutomations(): void
    {
        $automations = DB::table('automations')
            ->where('is_active', true)
            ->where('trigger_type', 'schedule')
            ->get();

        foreach ($automations as $automation) {
            $config = json_decode($automation->trigger_config, true);

            if (!$this->shouldRunSchedule($config, $automation->last_run_at)) {
                continue;
            }

            try {
                $projects = $this->getScheduleMatchedProjects($config);
                if ($projects->isEmpty()) {
                    $this->logRun($automation->id, 'success', 'No matching projects found.', []);
                    $this->markRun($automation->id);
                    continue;
                }

                $this->executeAction($automation, $projects);
                $this->markRun($automation->id);
                $this->logRun($automation->id, 'success', "Processed {$projects->count()} projects.", [
                    'project_ids' => $projects->pluck('id')->toArray(),
                ]);
            } catch (\Throwable $e) {
                Log::error("Automation #{$automation->id} failed: " . $e->getMessage());
                $this->logRun($automation->id, 'failed', $e->getMessage());
            }
        }
    }

    /**
     * Process event-based automations when a stage changes.
     */
    public function processStageChange(int $projectId, ?string $fromStage, string $toStage): void
    {
        $automations = DB::table('automations')
            ->where('is_active', true)
            ->where('trigger_type', 'stage_change')
            ->get();

        foreach ($automations as $automation) {
            $config = json_decode($automation->trigger_config, true);

            $matchFrom = ($config['from_stage'] ?? '*') === '*' || ($config['from_stage'] ?? '') === $fromStage;
            $matchTo = ($config['to_stage'] ?? '*') === '*' || ($config['to_stage'] ?? '') === $toStage;

            if (!$matchFrom || !$matchTo) continue;

            $project = DB::table('projects')
                ->leftJoin('team_members as owner', 'projects.owner_id', '=', 'owner.id')
                ->select('projects.*', 'owner.name as owner_name')
                ->where('projects.id', $projectId)
                ->first();

            if (!$project) continue;

            try {
                $this->executeAction($automation, collect([$project]));
                $this->logRun($automation->id, 'success', "Stage change trigger: {$fromStage} -> {$toStage} on project #{$projectId}", [
                    'project_id' => $projectId,
                    'from_stage' => $fromStage,
                    'to_stage'   => $toStage,
                ]);
            } catch (\Throwable $e) {
                Log::error("Automation #{$automation->id} stage trigger failed: " . $e->getMessage());
                $this->logRun($automation->id, 'failed', $e->getMessage());
            }
        }
    }

    /**
     * Process event-based automations when project status changes.
     */
    public function processStatusChange(int $projectId, ?string $fromStatus, string $toStatus): void
    {
        $automations = DB::table('automations')
            ->where('is_active', true)
            ->where('trigger_type', 'status_change')
            ->get();

        foreach ($automations as $automation) {
            $config = json_decode($automation->trigger_config, true);

            $matchFrom = ($config['from_status'] ?? '*') === '*' || ($config['from_status'] ?? '') === $fromStatus;
            $matchTo = ($config['to_status'] ?? '*') === '*' || ($config['to_status'] ?? '') === $toStatus;

            if (!$matchFrom || !$matchTo) continue;

            $project = DB::table('projects')
                ->leftJoin('team_members as owner', 'projects.owner_id', '=', 'owner.id')
                ->select('projects.*', 'owner.name as owner_name')
                ->where('projects.id', $projectId)
                ->first();

            if (!$project) continue;

            try {
                $this->executeAction($automation, collect([$project]));
                $this->logRun($automation->id, 'success', "Status change trigger: {$fromStatus} -> {$toStatus} on project #{$projectId}", [
                    'project_id'  => $projectId,
                    'from_status' => $fromStatus,
                    'to_status'   => $toStatus,
                ]);
            } catch (\Throwable $e) {
                Log::error("Automation #{$automation->id} status trigger failed: " . $e->getMessage());
                $this->logRun($automation->id, 'failed', $e->getMessage());
            }
        }
    }

    /**
     * Process blocker-created automations.
     */
    public function processBlockerCreated(int $projectId, object $blocker): void
    {
        $automations = DB::table('automations')
            ->where('is_active', true)
            ->where('trigger_type', 'blocker_created')
            ->get();

        foreach ($automations as $automation) {
            $project = DB::table('projects')
                ->leftJoin('team_members as owner', 'projects.owner_id', '=', 'owner.id')
                ->select('projects.*', 'owner.name as owner_name')
                ->where('projects.id', $projectId)
                ->first();

            if (!$project) continue;

            try {
                $this->executeAction($automation, collect([$project]), ['blocker' => $blocker]);
                $this->logRun($automation->id, 'success', "Blocker created on project #{$projectId}", [
                    'project_id' => $projectId,
                    'blocker_id' => $blocker->id ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::error("Automation #{$automation->id} blocker trigger failed: " . $e->getMessage());
                $this->logRun($automation->id, 'failed', $e->getMessage());
            }
        }
    }

    // ── Internals ──────────────────────────────────────────

    private function shouldRunSchedule(array $config, ?string $lastRunAt): bool
    {
        $now = now();
        $frequency = $config['frequency'] ?? 'weekly';
        $dayOfWeek = $config['day_of_week'] ?? 1; // 1 = Monday
        $timeStr = $config['time'] ?? '09:00';

        // Don't run more than once per period
        if ($lastRunAt) {
            $last = \Carbon\Carbon::parse($lastRunAt);
            if ($frequency === 'daily' && $last->isToday()) return false;
            if ($frequency === 'weekly' && $last->isSameWeek($now)) return false;
            if ($frequency === 'monthly' && $last->isSameMonth($now)) return false;
        }

        if ($frequency === 'weekly' && $now->dayOfWeekIso !== (int) $dayOfWeek) {
            return false;
        }

        if ($frequency === 'monthly' && $now->day !== (int) ($config['day_of_month'] ?? 1)) {
            return false;
        }

        // Check time window (run within 30 min of target)
        [$h, $m] = explode(':', $timeStr);
        $targetMinutes = (int) $h * 60 + (int) $m;
        $nowMinutes = $now->hour * 60 + $now->minute;

        return abs($nowMinutes - $targetMinutes) <= 30;
    }

    private function getScheduleMatchedProjects(array $config): \Illuminate\Support\Collection
    {
        $condition = $config['condition'] ?? 'stage_is_live';
        $lookbackDays = $config['lookback_days'] ?? 7;
        $since = now()->subDays($lookbackDays);

        $query = DB::table('projects')
            ->leftJoin('team_members as owner', 'projects.owner_id', '=', 'owner.id')
            ->select('projects.*', 'owner.name as owner_name')
            ->whereNull('projects.deleted_at')
            ->where(function ($q) {
                $q->whereNull('projects.custom_task_type')
                  ->orWhere('projects.custom_task_type', '!=', 'worklog_custom_project');
            });

        switch ($condition) {
            case 'stage_is_live':
                // Projects whose latest stage is 'live' and stage was set in lookback period
                $query->whereExists(function ($sub) use ($since) {
                    $sub->select(DB::raw(1))
                        ->from('project_stages')
                        ->whereColumn('project_stages.project_id', 'projects.id')
                        ->where('project_stages.stage_name', 'live')
                        ->where('project_stages.created_at', '>=', $since);
                });
                break;

            case 'stage_changed':
                $query->whereExists(function ($sub) use ($since) {
                    $sub->select(DB::raw(1))
                        ->from('project_stages')
                        ->whereColumn('project_stages.project_id', 'projects.id')
                        ->where('project_stages.created_at', '>=', $since);
                });
                break;

            case 'overdue':
                $query->where('projects.due_date', '<', now()->toDateString())
                      ->where('projects.status', 'active');
                break;

            case 'all_active':
                $query->where('projects.status', 'active');
                break;

            case 'blockers_open':
                $query->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('project_blockers')
                        ->whereColumn('project_blockers.project_id', 'projects.id')
                        ->where('project_blockers.status', 'active');
                });
                break;
        }

        return $query->get();
    }

    private function executeAction(object $automation, \Illuminate\Support\Collection $projects, array $extra = []): void
    {
        $actionConfig = json_decode($automation->action_config, true);
        $actionType = $automation->action_type;

        $recipients = $this->resolveRecipients($actionConfig, $projects);

        if ($recipients->isEmpty()) return;

        if ($actionType === 'send_email') {
            $subject = $actionConfig['subject'] ?? "Automation: {$automation->name}";
            $body = $this->buildEmailBody($automation, $actionConfig, $projects, $extra);

            foreach ($recipients as $recipient) {
                try {
                    Mail::to($recipient->email)->send(new AutomationEmail($subject, $body, $automation->name));
                } catch (\Throwable $e) {
                    Log::warning("Automation email to {$recipient->email} failed: " . $e->getMessage());
                }
            }
        }

        if ($actionType === 'send_notification') {
            $title = $actionConfig['title'] ?? $automation->name;

            foreach ($recipients as $recipient) {
                foreach ($projects as $project) {
                    $msg = str_replace('{{project_name}}', $project->name, $actionConfig['message'] ?? 'Automation triggered for {{project_name}}');

                    DB::table('notifications')->insert([
                        'user_id'    => $recipient->id,
                        'type'       => 'automation',
                        'title'      => str_replace('{{project_name}}', $project->name, $title),
                        'message'    => $msg,
                        'data'       => json_encode(['project_id' => $project->id, 'automation_id' => $automation->id]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function resolveRecipients(array $actionConfig, \Illuminate\Support\Collection $projects): \Illuminate\Support\Collection
    {
        $recipientType = $actionConfig['recipients'] ?? 'all_assignees';

        if ($recipientType === 'all_assignees') {
            $userIds = collect();
            foreach ($projects as $p) {
                if ($p->owner_id) $userIds->push($p->owner_id);
                if (isset($p->analyst_id) && $p->analyst_id) $userIds->push($p->analyst_id);
                if (isset($p->developer_id) && $p->developer_id) $userIds->push($p->developer_id);
                if (isset($p->analyst_testing_id) && $p->analyst_testing_id) $userIds->push($p->analyst_testing_id);
            }
            $userIds = $userIds->unique();

            return DB::table('team_members')
                ->whereIn('id', $userIds->toArray())
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->get();
        }

        if ($recipientType === 'specific_roles') {
            $roles = $actionConfig['roles'] ?? [];
            return DB::table('team_members')
                ->whereIn('role', $roles)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->get();
        }

        if ($recipientType === 'specific_users') {
            $ids = $actionConfig['user_ids'] ?? [];
            return DB::table('team_members')
                ->whereIn('id', $ids)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->get();
        }

        return collect();
    }

    private function buildEmailBody(object $automation, array $config, \Illuminate\Support\Collection $projects, array $extra): string
    {
        $lines = [];
        $lines[] = $config['email_body'] ?? "This is an automated notification from: <strong>{$automation->name}</strong>";
        $lines[] = '';

        if ($projects->count() > 0) {
            $lines[] = '<strong>Projects (' . $projects->count() . '):</strong>';
            $lines[] = '<ul>';
            foreach ($projects as $p) {
                $owner = $p->owner_name ?? 'Unassigned';
                $lines[] = "<li><strong>{$p->name}</strong> — Owner: {$owner}, Status: {$p->status}</li>";
            }
            $lines[] = '</ul>';
        }

        if (!empty($extra['blocker'])) {
            $b = $extra['blocker'];
            $lines[] = "<p><strong>Blocker:</strong> {$b->description}</p>";
        }

        return implode("\n", $lines);
    }

    private function logRun(int $automationId, string $status, string $message, array $details = []): void
    {
        DB::table('automation_logs')->insert([
            'automation_id' => $automationId,
            'status'        => $status,
            'message'       => $message,
            'details'       => json_encode($details),
            'created_at'    => now(),
        ]);
    }

    private function markRun(int $automationId): void
    {
        DB::table('automations')->where('id', $automationId)->update(['last_run_at' => now()]);
    }
}
