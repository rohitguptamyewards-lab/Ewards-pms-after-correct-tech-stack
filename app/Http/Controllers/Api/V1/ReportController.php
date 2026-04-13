<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function projects(Request $request)
    {
        $role = $this->authRole();
        abort_unless(in_array($role, ['manager', 'analyst_head', 'analyst']), 403);

        $projects = DB::table('projects')
            ->leftJoin('team_members', 'projects.owner_id', '=', 'team_members.id')
            ->select(
                'projects.*',
                'team_members.name as owner_name',
                DB::raw('(SELECT COUNT(*) FROM project_planners WHERE project_planners.project_id = projects.id) as planner_count'),
                DB::raw('(SELECT COUNT(*) FROM project_planners WHERE project_planners.project_id = projects.id AND project_planners.status = \'done\') as planner_done_count'),
                DB::raw('(SELECT COUNT(*) FROM project_blockers WHERE project_blockers.project_id = projects.id AND project_blockers.status = \'active\') as active_blockers'),
                DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage'),
                DB::raw('(SELECT COALESCE(SUM(wl.hours_spent), 0) FROM work_logs wl WHERE wl.project_id = projects.id AND wl.deleted_at IS NULL) as total_hours'),
                DB::raw('(SELECT COUNT(DISTINCT pw.user_id) FROM project_workers pw WHERE pw.project_id = projects.id) as contributor_count')
            )
            ->whereNull('projects.deleted_at')
            ->where(fn ($q) => $q->whereNull('projects.custom_task_type')->orWhere('projects.custom_task_type', '!=', 'worklog_custom_project'))
            ->orderByDesc('projects.created_at')
            ->get();

        $projects = $projects->map(function ($project) {
            $project->progress_percent = $this->calculateProjectProgress(
                $project->current_stage ?? null,
                $project->status ?? null
            );
            return $project;
        });

        if ($request->wantsJson()) {
            return response()->json($projects);
        }

        return Inertia::render('Reports/Projects', [
            'projects' => $projects,
        ]);
    }

    public function workers(Request $request)
    {
        $role = $this->authRole();
        abort_unless(auth()->check(), 403);

        $monthStart = now()->startOfMonth()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();

        $workers = DB::table('team_members')
            ->select(
                'team_members.id', 'team_members.name', 'team_members.email', 'team_members.role',
                'team_members.is_active', 'team_members.joined_date',
                DB::raw('(SELECT COUNT(DISTINCT pw.project_id) FROM project_workers pw WHERE pw.user_id = team_members.id) as project_count'),
                DB::raw('(SELECT COUNT(DISTINCT pw.project_id) FROM project_workers pw INNER JOIN projects p ON pw.project_id = p.id WHERE pw.user_id = team_members.id AND p.status = \'active\' AND p.deleted_at IS NULL) as current_projects'),
                DB::raw('(SELECT COALESCE(SUM(wl.hours_spent), 0) FROM work_logs wl WHERE wl.user_id = team_members.id AND wl.deleted_at IS NULL) as total_hours'),
                DB::raw("(SELECT COALESCE(SUM(wl.hours_spent), 0) FROM work_logs wl WHERE wl.user_id = team_members.id AND wl.deleted_at IS NULL AND wl.log_date >= ?) as month_hours"),
                DB::raw("(SELECT COALESCE(SUM(wl.hours_spent), 0) FROM work_logs wl WHERE wl.user_id = team_members.id AND wl.deleted_at IS NULL AND wl.log_date >= ?) as week_hours")
            )
            ->addBinding([$monthStart, $weekStart], 'select')
            ->whereNull('team_members.deleted_at')
            ->where('team_members.is_active', true)
            ->orderBy('team_members.name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json($workers);
        }

        return Inertia::render('Reports/Workers', [
            'workers' => $workers,
        ]);
    }

    /**
     * Comprehensive Report Dashboard for managers/analysts
     */
    public function dashboard(Request $request)
    {
        $role = $this->authRole();
        abort_unless($this->canAccessDashboard($role), 403);
        $canViewSensitiveSections = $this->canViewSensitiveDashboardSections($role);

        // 1. All projects with timeline data for Gantt (excluding custom works)
        $projects = DB::table('projects')
            ->leftJoin('team_members as owner', 'projects.owner_id', '=', 'owner.id')
            ->select(
                'projects.id', 'projects.name', 'projects.status', 'projects.priority',
                'projects.created_at', 'projects.updated_at',
                'owner.name as owner_name',
                DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage'),
                DB::raw('(SELECT COUNT(*) FROM project_planners WHERE project_planners.project_id = projects.id) as planner_count'),
                DB::raw('(SELECT COUNT(*) FROM project_planners WHERE project_planners.project_id = projects.id AND project_planners.status = \'done\') as planner_done'),
                DB::raw('(SELECT MIN(pp.due_date) FROM project_planners pp WHERE pp.project_id = projects.id AND pp.due_date IS NOT NULL AND pp.status != \'done\') as next_deadline'),
                DB::raw('(SELECT MAX(pp.due_date) FROM project_planners pp WHERE pp.project_id = projects.id AND pp.due_date IS NOT NULL) as last_deadline'),
                DB::raw('(SELECT COALESCE(SUM(wl.hours_spent), 0) FROM work_logs wl WHERE wl.project_id = projects.id AND wl.deleted_at IS NULL) as total_hours')
            )
            ->whereNull('projects.deleted_at')
            ->where(fn ($q) => $q->whereNull('projects.custom_task_type')->orWhere('projects.custom_task_type', '!=', 'worklog_custom_project'))
            ->orderByDesc('projects.created_at')
            ->get();

        // 2. All upcoming deadlines for calendar
        $deadlines = DB::table('project_planners')
            ->join('projects', 'project_planners.project_id', '=', 'projects.id')
            ->leftJoin('team_members', 'project_planners.assigned_to', '=', 'team_members.id')
            ->select(
                'project_planners.id', 'project_planners.title', 'project_planners.due_date',
                'project_planners.status', 'project_planners.milestone_flag',
                'projects.id as project_id', 'projects.name as project_name', 'projects.priority',
                'team_members.name as assignee_name'
            )
            ->whereNotNull('project_planners.due_date')
            ->whereNull('projects.deleted_at')
            ->orderBy('project_planners.due_date')
            ->get();

        $projectWorklogs = collect();
        $teamUtilization = collect();
        $recentLogs = collect();

        if ($canViewSensitiveSections) {
            // 3. Project-wise employee worklog summary
            $projectWorklogs = DB::table('work_logs')
                ->join('projects', 'work_logs.project_id', '=', 'projects.id')
                ->join('team_members', 'work_logs.user_id', '=', 'team_members.id')
                ->select(
                    'projects.id as project_id', 'projects.name as project_name',
                    'team_members.id as user_id', 'team_members.name as user_name',
                    DB::raw('SUM(work_logs.hours_spent) as total_hours'),
                    DB::raw('COUNT(*) as log_count'),
                    DB::raw('MIN(work_logs.log_date) as first_log'),
                    DB::raw('MAX(work_logs.log_date) as last_log')
                )
                ->whereNull('work_logs.deleted_at')
                ->whereNull('projects.deleted_at')
                ->groupBy('projects.id', 'projects.name', 'team_members.id', 'team_members.name')
                ->orderBy('projects.name')
                ->orderByDesc(DB::raw('SUM(work_logs.hours_spent)'))
                ->get();

            // 4. Team member utilization (last 30 days)
            $thirtyDaysAgo = now()->subDays(30)->toDateString();
            $sevenDaysAgo = now()->subDays(7)->toDateString();
            $teamUtilization = DB::table('team_members')
                ->select(
                    'team_members.id', 'team_members.name', 'team_members.role',
                    DB::raw("(SELECT COALESCE(SUM(wl.hours_spent), 0) FROM work_logs wl WHERE wl.user_id = team_members.id AND wl.deleted_at IS NULL AND wl.log_date >= ?) as month_hours"),
                    DB::raw("(SELECT COALESCE(SUM(wl.hours_spent), 0) FROM work_logs wl WHERE wl.user_id = team_members.id AND wl.deleted_at IS NULL AND wl.log_date >= ?) as week_hours"),
                    DB::raw("(SELECT COUNT(DISTINCT wl.project_id) FROM work_logs wl WHERE wl.user_id = team_members.id AND wl.deleted_at IS NULL AND wl.log_date >= ?) as active_projects")
                )
                ->addBinding([$thirtyDaysAgo, $sevenDaysAgo, $thirtyDaysAgo], 'select')
                ->whereNull('team_members.deleted_at')
                ->where('team_members.is_active', true)
                ->orderBy('team_members.name')
                ->get();

            // 5. Recent work logs (last 7 days) for activity feed
            $recentLogs = DB::table('work_logs')
                ->join('team_members', 'work_logs.user_id', '=', 'team_members.id')
                ->join('projects', 'work_logs.project_id', '=', 'projects.id')
                ->select(
                    'work_logs.*',
                    'team_members.name as user_name',
                    'projects.name as project_name'
                )
                ->whereNull('work_logs.deleted_at')
                ->where('work_logs.log_date', '>=', now()->subDays(7)->toDateString())
                ->orderByDesc('work_logs.log_date')
                ->orderByDesc('work_logs.created_at')
                ->limit(50)
                ->get();
        }

        if ($request->wantsJson()) {
            return response()->json(compact('projects', 'deadlines', 'projectWorklogs', 'teamUtilization', 'recentLogs', 'canViewSensitiveSections'));
        }

        return Inertia::render('Reports/Dashboard', [
            'projects'         => $projects,
            'deadlines'        => $deadlines,
            'projectWorklogs'  => $projectWorklogs,
            'teamUtilization'  => $teamUtilization,
            'recentLogs'       => $recentLogs,
            'canViewSensitiveSections' => $canViewSensitiveSections,
        ]);
    }

    /**
     * Get worklogs for a specific project (used in project detail report tab)
     */
    public function projectWorklogs(Request $request, int $projectId)
    {
        $role = $this->authRole();
        abort_unless($this->canViewSensitiveDashboardSections($role), 403);

        $worklogs = DB::table('work_logs')
            ->join('team_members', 'work_logs.user_id', '=', 'team_members.id')
            ->select(
                'work_logs.*',
                'team_members.name as user_name',
                'team_members.role as user_role'
            )
            ->where('work_logs.project_id', $projectId)
            ->whereNull('work_logs.deleted_at')
            ->orderByDesc('work_logs.log_date')
            ->orderByDesc('work_logs.start_time')
            ->get();

        // Per-employee summary
        $employeeSummary = DB::table('work_logs')
            ->join('team_members', 'work_logs.user_id', '=', 'team_members.id')
            ->select(
                'team_members.id as user_id', 'team_members.name as user_name',
                'team_members.role as user_role',
                DB::raw('SUM(work_logs.hours_spent) as total_hours'),
                DB::raw('COUNT(*) as log_count'),
                DB::raw('MIN(work_logs.log_date) as first_log'),
                DB::raw('MAX(work_logs.log_date) as last_log'),
                DB::raw('SUM(CASE WHEN work_logs.status = \'done\' THEN 1 ELSE 0 END) as done_count'),
                DB::raw('SUM(CASE WHEN work_logs.status = \'in_progress\' THEN 1 ELSE 0 END) as in_progress_count'),
                DB::raw('SUM(CASE WHEN work_logs.status = \'blocked\' THEN 1 ELSE 0 END) as blocked_count')
            )
            ->where('work_logs.project_id', $projectId)
            ->whereNull('work_logs.deleted_at')
            ->groupBy('team_members.id', 'team_members.name', 'team_members.role')
            ->orderByDesc(DB::raw('SUM(work_logs.hours_spent)'))
            ->get();

        return response()->json([
            'worklogs'        => $worklogs,
            'employeeSummary' => $employeeSummary,
        ]);
    }

    /**
     * Get worklog details for a specific team member (for Workers Report detail view)
     */
    public function memberWorklogs(Request $request, int $memberId)
    {
        $role = $this->authRole();
        abort_unless(auth()->check(), 403);

        $member = DB::table('team_members')->where('id', $memberId)->first();
        if (!$member) abort(404);

        // Current projects (active)
        $currentProjects = DB::table('projects')
            ->where(function ($q) use ($memberId) {
                $q->where('owner_id', $memberId)
                  ->orWhere('analyst_id', $memberId)
                  ->orWhere('developer_id', $memberId)
                  ->orWhere('analyst_testing_id', $memberId)
                  ->orWhereExists(function ($sub) use ($memberId) {
                      $sub->select(DB::raw(1))
                          ->from('project_workers')
                          ->whereColumn('project_workers.project_id', 'projects.id')
                          ->where('project_workers.user_id', $memberId);
                  });
            })
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->select('id', 'name', 'status', 'priority')
            ->get();

        // Lifetime projects
        $lifetimeProjects = DB::table('projects')
            ->where(function ($q) use ($memberId) {
                $q->where('owner_id', $memberId)
                  ->orWhere('analyst_id', $memberId)
                  ->orWhere('developer_id', $memberId)
                  ->orWhere('analyst_testing_id', $memberId)
                  ->orWhereExists(function ($sub) use ($memberId) {
                      $sub->select(DB::raw(1))
                          ->from('project_workers')
                          ->whereColumn('project_workers.project_id', 'projects.id')
                          ->where('project_workers.user_id', $memberId);
                  });
            })
            ->whereNull('deleted_at')
            ->select('id', 'name', 'status', 'priority')
            ->get();

        // Recent worklogs (last 30 days)
        $recentWorklogs = DB::table('work_logs')
            ->join('projects', 'work_logs.project_id', '=', 'projects.id')
            ->select(
                'work_logs.*',
                'projects.name as project_name'
            )
            ->where('work_logs.user_id', $memberId)
            ->whereNull('work_logs.deleted_at')
            ->where('work_logs.log_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('work_logs.log_date')
            ->orderByDesc('work_logs.start_time')
            ->get();

        // Per-project hour summary
        $projectHours = DB::table('work_logs')
            ->join('projects', 'work_logs.project_id', '=', 'projects.id')
            ->select(
                'projects.id as project_id',
                'projects.name as project_name',
                DB::raw('SUM(work_logs.hours_spent) as total_hours'),
                DB::raw('COUNT(*) as log_count')
            )
            ->where('work_logs.user_id', $memberId)
            ->whereNull('work_logs.deleted_at')
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc(DB::raw('SUM(work_logs.hours_spent)'))
            ->get();

        return response()->json([
            'member' => $member,
            'currentProjects' => $currentProjects,
            'lifetimeProjects' => $lifetimeProjects,
            'recentWorklogs' => $recentWorklogs,
            'projectHours' => $projectHours,
        ]);
    }

    private function authRole(): string
    {
        $role = auth()->user()?->role;
        return $role instanceof \App\Enums\Role ? $role->value : (string) ($role ?? '');
    }

    private function canAccessDashboard(string $role): bool
    {
        return in_array($role, ['manager', 'analyst_head', 'analyst', 'senior_developer'], true);
    }

    private function canViewSensitiveDashboardSections(string $role): bool
    {
        return in_array($role, ['manager', 'analyst_head', 'senior_developer'], true);
    }

    private function calculateProjectProgress(?string $currentStage, ?string $status): int
    {
        if ($status === 'completed' || in_array($currentStage, [
            'live',
            'yet_to_announce_on_group',
            'yet_to_put_on_cron',
            'ticket_raised_for_revenue',
        ], true)) {
            return 100;
        }

        if (in_array($currentStage, [
            'ready_to_go_for_live',
            'live_testing_yet_to_start',
            'live_testing_wip',
            'bugs_reported_live',
            'bug_fixing_wip',
            'bugs_fixed',
        ], true)) {
            return 66;
        }

        if (in_array($currentStage, [
            'ready_for_internal',
            'testing_wip_internal',
            'bugs_reported_test_internal',
            'bugs_reported_testing',
            'bugs_reported_internal',
        ], true)) {
            return 33;
        }

        return 0;
    }
}
