<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkLogRequest;
use App\Repositories\WorkLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkLogController extends Controller
{
    public function __construct(
        private readonly WorkLogRepository $workLogRepository,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->authRole();
        $filters = $request->only(['user_id', 'project_id', 'date_from', 'date_to', 'status']);

        // Only select roles can view everyone's logs
        if (!$this->canViewAllWorklogs($role)) {
            $filters['user_id'] = $user->id;
        }

        $workLogs = $this->workLogRepository->findAll($filters);

        // Get all projects for the dropdown (show project names + creator for custom projects)
        $projects = DB::table('projects')
            ->leftJoin('team_members as creator', 'projects.created_by', '=', 'creator.id')
            ->select('projects.id', 'projects.name', 'projects.custom_task_type', 'creator.name as created_by_name')
            ->whereNull('projects.deleted_at')
            ->orderBy('projects.name')
            ->get();

        // Get team members for filter dropdown (privileged roles only)
        $teamMembers = $this->canViewAllWorklogs($role)
            ? DB::table('team_members')
                ->select('id', 'name', 'role')
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        // Calculate week total for current user
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();
        $weekTotal = $this->workLogRepository->sumHoursForUser(
            $filters['user_id'] ?? $user->id,
            $weekStart,
            $weekEnd
        );

        if ($request->wantsJson()) {
            return response()->json($workLogs);
        }

        return Inertia::render('WorkLogs/Index', [
            'workLogs'    => $workLogs,
            'projects'    => $projects,
            'teamMembers' => $teamMembers,
            'weekTotal'   => round($weekTotal, 2),
            'filters'     => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $projects = DB::table('projects')
            ->leftJoin('team_members as creator', 'projects.created_by', '=', 'creator.id')
            ->select('projects.id', 'projects.name', 'projects.custom_task_type', 'creator.name as created_by_name')
            ->whereNull('projects.deleted_at')
            ->orderBy('projects.name')
            ->get();

        // Auto-fill last end time as new start time
        $lastEndTime = $this->workLogRepository->getLastEndTime(
            auth()->id(),
            now()->toDateString()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'projects'     => $projects,
                'lastEndTime'  => $lastEndTime,
            ]);
        }

        return Inertia::render('WorkLogs/Create', [
            'projects'    => $projects,
            'lastEndTime' => $lastEndTime,
        ]);
    }

    public function store(StoreWorkLogRequest $request)
    {
        $data = $request->validated();
        $userId = auth()->id();
        $data['user_id'] = $userId;

        if (empty($data['project_id']) && !empty($data['project_name'])) {
            $data['project_id'] = $this->findOrCreateCustomProject(trim($data['project_name']), $userId);
        }
        unset($data['project_name']);

        $id = $this->workLogRepository->create($data);

        if ($request->wantsJson()) {
            return response()->json($this->workLogRepository->findById($id), 201);
        }

        return redirect()->route('work-logs.index')
            ->with('success', 'Work log added successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $log = $this->workLogRepository->findById($id);

        if (!$log) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Only own logs or manager
        $role = $this->authRole();
        if ($log->user_id !== auth()->id() && !$this->canViewAllWorklogs($role)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'project_id' => ['sometimes', 'integer', 'exists:projects,id'],
            'log_date'   => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time'   => ['sometimes', 'date_format:H:i'],
            'status'     => ['sometimes', 'nullable', 'in:done,in_progress,blocked'],
            'note'       => ['nullable', 'string'],
            'blocker'    => ['nullable', 'string'],
        ]);

        $effectiveStart = $data['start_time'] ?? $log->start_time;
        $effectiveEnd = $data['end_time'] ?? $log->end_time;
        if (!empty($effectiveStart) && !empty($effectiveEnd) && strtotime($effectiveEnd) <= strtotime($effectiveStart)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'end_time' => ['End time must be after start time.'],
                ],
            ], 422);
        }

        $this->workLogRepository->update($id, $data);

        return response()->json($this->workLogRepository->findById($id));
    }

    public function destroy(int $id): JsonResponse
    {
        $log = $this->workLogRepository->findById($id);

        if (!$log) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $role = $this->authRole();
        if ($log->user_id !== auth()->id() && !$this->canViewAllWorklogs($role)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $this->workLogRepository->delete($id);

        return response()->json(['message' => 'Deleted']);
    }

    private function authRole(): string
    {
        $role = auth()->user()?->role;
        return $role instanceof \App\Enums\Role ? $role->value : (string) ($role ?? '');
    }

    private function canViewAllWorklogs(?string $role = null): bool
    {
        $effectiveRole = $role ?? $this->authRole();
        return in_array($effectiveRole, ['manager', 'analyst_head', 'senior_developer'], true);
    }

    private function findOrCreateCustomProject(string $projectName, int $userId): int
    {
        $existingProjectId = DB::table('projects')
            ->whereRaw('LOWER(name) = ?', [strtolower($projectName)])
            ->whereNull('deleted_at')
            ->value('id');

        if ($existingProjectId) {
            return (int) $existingProjectId;
        }

        $now = now();
        $projectId = DB::table('projects')->insertGetId([
            'name'             => $projectName,
            'description'      => 'Created from Work Log',
            'status'           => 'active',
            'priority'         => 'medium',
            'owner_id'         => $userId,
            'created_by'       => $userId,
            'work_type'        => 'other',
            'task_type'        => 'other',
            'custom_task_type' => 'worklog_custom_project',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        DB::table('project_workers')->insert([
            'project_id'  => $projectId,
            'user_id'     => $userId,
            'role'        => 'owner',
            'assigned_by' => $userId,
            'assigned_at' => $now,
        ]);

        return (int) $projectId;
    }
}
