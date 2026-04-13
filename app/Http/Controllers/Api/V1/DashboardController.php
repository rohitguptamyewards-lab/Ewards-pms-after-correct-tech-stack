<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    private const MANAGER_DASHBOARD_ROLES = ['manager', 'analyst_head', 'analyst'];
    private const TEAM_ACTIVITY_REPORT_ROLES = ['manager', 'analyst_head', 'senior_developer'];
    private const WORKLOG_CUSTOM_TASK_TYPE = 'worklog_custom_project';

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->authRole();

        if (in_array($role, self::MANAGER_DASHBOARD_ROLES, true)) {
            return $this->renderManagerDashboard($role);
        }

        return $this->renderEmployeeDashboard($user->id, $role);
    }

    private function renderManagerDashboard(string $role)
    {
        $totalProjects = DB::table('projects')->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('custom_task_type')->orWhere('custom_task_type', '!=', self::WORKLOG_CUSTOM_TASK_TYPE))
            ->count();
        $activeProjects = DB::table('projects')->whereNull('deleted_at')->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('custom_task_type')->orWhere('custom_task_type', '!=', self::WORKLOG_CUSTOM_TASK_TYPE))
            ->count();
        $onHoldProjects = DB::table('projects')->whereNull('deleted_at')->where('status', 'on_hold')
            ->where(fn ($q) => $q->whereNull('custom_task_type')->orWhere('custom_task_type', '!=', self::WORKLOG_CUSTOM_TASK_TYPE))
            ->count();

        $activeBlockers = DB::table('project_blockers')
            ->join('projects', 'project_blockers.project_id', '=', 'projects.id')
            ->leftJoin('team_members', 'project_blockers.created_by', '=', 'team_members.id')
            ->select('project_blockers.*', 'projects.name as project_name', 'team_members.name as creator_name')
            ->where('project_blockers.status', 'active')
            ->whereNull('projects.deleted_at')
            ->orderByDesc('project_blockers.created_at')
            ->limit(10)
            ->get();

        $overduePlanners = DB::table('project_planners')
            ->join('projects', 'project_planners.project_id', '=', 'projects.id')
            ->leftJoin('team_members', 'project_planners.assigned_to', '=', 'team_members.id')
            ->select('project_planners.*', 'projects.name as project_name', 'team_members.name as assignee_name')
            ->where('project_planners.status', '!=', 'done')
            ->whereNotNull('project_planners.due_date')
            ->where('project_planners.due_date', '<', now()->toDateString())
            ->whereNull('projects.deleted_at')
            ->orderBy('project_planners.due_date')
            ->limit(10)
            ->get();

        $recentProjects = DB::table('projects')
            ->leftJoin('team_members', 'projects.owner_id', '=', 'team_members.id')
            ->select(
                'projects.id', 'projects.name', 'projects.status', 'projects.priority',
                'team_members.name as owner_name',
                DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage')
            )
            ->whereNull('projects.deleted_at')
            ->where(fn ($q) => $q->whereNull('projects.custom_task_type')->orWhere('projects.custom_task_type', '!=', self::WORKLOG_CUSTOM_TASK_TYPE))
            ->orderByDesc('projects.updated_at')
            ->limit(10)
            ->get();

        [$projectsForReport, $teamMembersForReport] = $this->getActivityReportFilterOptions($role);

        return Inertia::render('Dashboard/Manager', [
            'totalProjects'            => $totalProjects,
            'activeProjects'           => $activeProjects,
            'onHoldProjects'           => $onHoldProjects,
            'activeBlockers'           => $activeBlockers,
            'overduePlanners'          => $overduePlanners,
            'recentProjects'           => $recentProjects,
            'canViewTeamActivityReport'=> $this->canViewTeamActivityReport($role),
            'projectsForReport'        => $projectsForReport,
            'teamMembersForReport'     => $teamMembersForReport,
        ]);
    }

    private function renderEmployeeDashboard(int $userId, string $role)
    {
        $myProjects = DB::table('projects')
            ->leftJoin('project_workers', function ($join) use ($userId) {
                $join->on('projects.id', '=', 'project_workers.project_id')
                     ->where('project_workers.user_id', '=', $userId);
            })
            ->leftJoin('team_members', 'projects.owner_id', '=', 'team_members.id')
            ->select(
                'projects.id', 'projects.name', 'projects.status', 'projects.priority',
                'projects.work_type', 'projects.developer_id', 'projects.analyst_testing_id',
                'project_workers.role as my_role',
                'team_members.name as owner_name',
                DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage')
            )
            ->where(function ($q) use ($userId) {
                $q->where('project_workers.user_id', $userId)
                  ->orWhere('projects.owner_id', $userId)
                  ->orWhere('projects.analyst_id', $userId)
                  ->orWhere('projects.analyst_testing_id', $userId)
                  ->orWhere('projects.developer_id', $userId);
            })
            ->whereNull('projects.deleted_at')
            ->where(fn ($q) => $q->whereNull('projects.custom_task_type')->orWhere('projects.custom_task_type', '!=', self::WORKLOG_CUSTOM_TASK_TYPE))
            ->distinct()
            ->orderByDesc('projects.updated_at')
            ->get();

        $myPlanners = DB::table('project_planners')
            ->join('projects', 'project_planners.project_id', '=', 'projects.id')
            ->select('project_planners.*', 'projects.name as project_name')
            ->where('project_planners.assigned_to', $userId)
            ->where('project_planners.status', '!=', 'done')
            ->whereNull('projects.deleted_at')
            ->orderBy('project_planners.due_date')
            ->get();

        $myBlockers = DB::table('project_blockers')
            ->join('projects', 'project_blockers.project_id', '=', 'projects.id')
            ->select('project_blockers.*', 'projects.name as project_name')
            ->where('project_blockers.created_by', $userId)
            ->where('project_blockers.status', 'active')
            ->whereNull('projects.deleted_at')
            ->get();

        [$projectsForReport, $teamMembersForReport] = $this->getActivityReportFilterOptions($role);

        return Inertia::render('Dashboard/Employee', [
            'myProjects'               => $myProjects,
            'myPlanners'               => $myPlanners,
            'myBlockers'               => $myBlockers,
            'canViewTeamActivityReport'=> $this->canViewTeamActivityReport($role),
            'projectsForReport'        => $projectsForReport,
            'teamMembersForReport'     => $teamMembersForReport,
        ]);
    }

    public function activityReport(Request $request): JsonResponse
    {
        abort_unless($this->canViewTeamActivityReport($this->authRole()), 403);

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'user_id'    => ['nullable', 'integer', 'exists:team_members,id'],
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
        ]);

        [$dateFrom, $dateTo] = $this->normalizeDateRange(
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null
        );

        $query = DB::table('work_logs')
            ->join('team_members', 'work_logs.user_id', '=', 'team_members.id')
            ->join('projects', 'work_logs.project_id', '=', 'projects.id')
            ->select(
                'work_logs.id',
                'work_logs.user_id',
                'work_logs.project_id',
                'work_logs.log_date',
                'work_logs.start_time',
                'work_logs.end_time',
                'work_logs.note',
                'work_logs.hours_spent',
                'work_logs.status',
                'work_logs.project_stage_snapshot',
                'team_members.name as user_name',
                'projects.name as project_name'
            )
            ->whereNull('work_logs.deleted_at')
            ->whereNull('projects.deleted_at')
            ->where(fn ($q) => $q->whereNull('projects.custom_task_type')->orWhere('projects.custom_task_type', '!=', self::WORKLOG_CUSTOM_TASK_TYPE))
            ->whereBetween('work_logs.log_date', [$dateFrom, $dateTo]);

        if (!empty($validated['project_id'])) {
            $query->where('work_logs.project_id', $validated['project_id']);
        }
        if (!empty($validated['user_id'])) {
            $query->where('work_logs.user_id', $validated['user_id']);
        }

        $logs = $query->orderBy('work_logs.log_date', 'desc')
            ->orderBy('team_members.name')
            ->get();

        $totalHours = round((float) $logs->sum('hours_spent'), 2);

        return response()->json([
            'logs'        => $logs,
            'total_hours' => $totalHours,
            'meta'        => [
                'date_from'   => $dateFrom,
                'date_to'     => $dateTo,
                'entry_count' => $logs->count(),
                'total_hours' => $totalHours,
            ],
        ]);
    }

    public function manager(Request $request): JsonResponse
    {
        $role = $this->authRole();
        abort_unless($role === 'manager', 403);
        return response()->json(['message' => 'Use web route']);
    }

    public function employee(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Use web route']);
    }

    private function authRole(): string
    {
        $role = auth()->user()?->role;

        return $role instanceof \App\Enums\Role ? $role->value : (string) ($role ?? '');
    }

    private function canViewTeamActivityReport(string $role): bool
    {
        return in_array($role, self::TEAM_ACTIVITY_REPORT_ROLES, true);
    }

    private function getActivityReportFilterOptions(string $role): array
    {
        if (!$this->canViewTeamActivityReport($role)) {
            return [collect(), collect()];
        }

        $projects = DB::table('projects')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('custom_task_type')->orWhere('custom_task_type', '!=', self::WORKLOG_CUSTOM_TASK_TYPE))
            ->orderBy('name')
            ->get();

        $teamMembers = DB::table('team_members')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return [$projects, $teamMembers];
    }

    private function normalizeDateRange(?string $dateFrom, ?string $dateTo): array
    {
        $from = $dateFrom ?: now()->toDateString();
        $to = $dateTo ?: $from;

        if ($from > $to) {
            return [$to, $from];
        }

        return [$from, $to];
    }
}
