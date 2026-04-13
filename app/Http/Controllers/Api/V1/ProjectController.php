<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Mail\ProjectAssigned;
use App\Repositories\ProjectRepository;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ProjectRepository $projectRepository,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->authRole();
        $uiFilters = $request->only(['status', 'priority', 'search', 'owner_id', 'work_type']);
        $filters = array_merge($uiFilters, ['project_scope' => 'default']);

        $projects = in_array($role, ['manager', 'analyst_head', 'analyst'])
            ? $this->projectRepository->findAll($filters)
            : $this->projectRepository->findByWorker($user->id, $filters);

        // All project names for dependency picker
        $allProjects = DB::table('projects')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('custom_task_type')
                  ->orWhere('custom_task_type', '!=', 'worklog_custom_project');
            })
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json($projects);
        }

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters'  => $uiFilters,
            'title'    => 'Projects',
            'basePath' => '/projects',
            'boardPath' => '/projects/board',
            'showBoardToggle' => true,
            'showCreateButton' => true,
            'allProjects' => $allProjects,
        ]);
    }

    public function board(Request $request)
    {
        $user = auth()->user();
        $role = $this->authRole();
        $uiFilters = $request->only(['status', 'priority', 'search', 'owner_id', 'work_type']);
        $filters = array_merge($uiFilters, ['project_scope' => 'default']);

        $projects = in_array($role, ['manager', 'analyst_head', 'analyst'])
            ? $this->projectRepository->findAll($filters)
            : $this->projectRepository->findByWorker($user->id, $filters);

        return Inertia::render('Projects/Board', [
            'projects' => $projects,
            'filters'  => $uiFilters,
        ]);
    }

    public function customWorklogIndex(Request $request)
    {
        $user = auth()->user();
        $role = $this->authRole();
        $uiFilters = $request->only(['status', 'priority', 'search', 'owner_id', 'work_type']);
        $filters = array_merge($uiFilters, ['project_scope' => 'worklog_custom']);

        $projects = in_array($role, ['manager', 'analyst_head', 'analyst'])
            ? $this->projectRepository->findAll($filters)
            : $this->projectRepository->findByWorker($user->id, $filters);

        if ($request->wantsJson()) {
            return response()->json($projects);
        }

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters'  => $uiFilters,
            'title'    => 'Custom Work',
            'basePath' => '/projects/custom-worklog',
            'boardPath' => '',
            'showBoardToggle' => false,
            'showCreateButton' => false,
            'allProjects' => [],
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(in_array($this->authRole(), ['manager', 'analyst_head', 'analyst']), 403);

        $teamMembers = DB::table('team_members')
            ->select('id', 'name', 'email', 'role')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $allProjects = DB::table('projects')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('custom_task_type')
                  ->orWhere('custom_task_type', '!=', 'worklog_custom_project');
            })
            ->orderBy('name')
            ->get();

        $parentId = $request->query('parent_id');
        $parentProject = null;
        if ($parentId) {
            $parentProject = DB::table('projects')->select('id', 'name')->where('id', $parentId)->first();
        }

        return Inertia::render('Projects/Create', [
            'teamMembers' => $teamMembers,
            'allProjects' => $allProjects,
            'parentProject' => $parentProject,
        ]);
    }

    public function store(StoreProjectRequest $request)
    {
        abort_unless(in_array($this->authRole(), ['manager', 'analyst_head', 'analyst']), 403);

        $data = $request->validated();
        $data['created_by'] = auth()->id();

        // Validate max depth of 3 (0-indexed, so 4 levels total)
        if (!empty($data['parent_id'])) {
            $parentDepth = $this->projectRepository->getDepth($data['parent_id']);
            if ($parentDepth >= 3) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'Maximum nesting depth (4 levels) reached.'], 422);
                }
                return back()->withErrors(['parent_id' => 'Maximum nesting depth (4 levels) reached.']);
            }
        }

        // Set analyst_id to current user if they're an analyst and didn't specify
        if (empty($data['analyst_id']) && $this->authRole() === 'analyst') {
            $data['analyst_id'] = auth()->id();
        }

        $id = $this->projectRepository->create($data);

        // Auto-create owner worker record
        DB::table('project_workers')->insert([
            'project_id'  => $id,
            'user_id'     => $data['owner_id'],
            'role'        => 'owner',
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
        ]);

        // Send email notifications for assignments
        $this->sendAssignmentEmails($id, $data);

        if ($request->wantsJson()) {
            return response()->json($this->projectRepository->findById($id), 201);
        }

        return redirect()->route('projects.show', $id)
            ->with('success', 'Project created successfully.');
    }

    public function show(Request $request, int $id)
    {
        $project = $this->projectService->getProjectWithDetails($id);

        if (!$project) {
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json($project);
        }

        $teamMembers = DB::table('team_members')
            ->select('id', 'name', 'email', 'role')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $attachments = DB::table('project_attachments')
            ->leftJoin('team_members', 'project_attachments.uploaded_by', '=', 'team_members.id')
            ->select('project_attachments.*', 'team_members.name as uploader_name')
            ->where('project_attachments.project_id', $id)
            ->orderByDesc('project_attachments.created_at')
            ->get();

        return Inertia::render('Projects/Show', array_merge($project, [
            'teamMembers' => $teamMembers,
            'attachments' => $attachments,
        ]));
    }

    /**
     * Get children (subtasks) of a project.
     */
    public function children(int $id): JsonResponse
    {
        $children = $this->projectRepository->findChildren($id);
        return response()->json($children);
    }

    /**
     * Replicate: return create page pre-filled with existing project data
     */
    public function replicate(Request $request, int $id)
    {
        abort_unless(in_array($this->authRole(), ['manager', 'analyst_head', 'analyst']), 403);

        $project = $this->projectRepository->findById($id);
        if (!$project) {
            abort(404);
        }

        $teamMembers = DB::table('team_members')
            ->select('id', 'name', 'email', 'role')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $allProjects = DB::table('projects')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('custom_task_type')
                  ->orWhere('custom_task_type', '!=', 'worklog_custom_project');
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('Projects/Create', [
            'teamMembers' => $teamMembers,
            'replicateFrom' => $project,
            'allProjects' => $allProjects,
        ]);
    }

    /**
     * Upload attachment for a project
     */
    public function uploadAttachment(Request $request, int $id)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        $path = $file->store('project-attachments/' . $id, 'public');

        $attachmentId = DB::table('project_attachments')->insertGetId([
            'project_id'    => $id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path'   => $path,
            'mime_type'     => $file->getClientMimeType(),
            'size'          => $file->getSize(),
            'uploaded_by'   => auth()->id(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $attachment = DB::table('project_attachments')
            ->leftJoin('team_members', 'project_attachments.uploaded_by', '=', 'team_members.id')
            ->select('project_attachments.*', 'team_members.name as uploader_name')
            ->where('project_attachments.id', $attachmentId)
            ->first();

        return response()->json($attachment, 201);
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment(int $id)
    {
        $attachment = DB::table('project_attachments')->where('id', $id)->first();
        if (!$attachment) {
            return response()->json(['message' => 'Not found'], 404);
        }

        \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->stored_path);
        DB::table('project_attachments')->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function update(UpdateProjectRequest $request, int $id)
    {
        $role = $this->authRole();

        if ($role === 'employee') {
            abort(403, 'Employees cannot edit project details. Use stage change or comments.');
        }

        $data = $request->validated();
        $oldProject = $this->projectRepository->findById($id);

        $this->projectRepository->update($id, $data);

        $this->sendAssignmentEmailsOnUpdate($id, $data, $oldProject);

        if ($request->wantsJson()) {
            return response()->json($this->projectRepository->findById($id));
        }

        return redirect()->route('projects.show', $id)
            ->with('success', 'Project updated successfully.');
    }

    private function sendAssignmentEmails(int $projectId, array $data): void
    {
        $project = $this->projectRepository->findById($projectId);
        $assignerName = auth()->user()->name;

        $assignments = [
            'developer_id'       => 'Developer',
            'analyst_testing_id' => 'Analyst Testing',
            'analyst_id'         => 'Analyst',
        ];

        foreach ($assignments as $field => $roleName) {
            if (!empty($data[$field])) {
                $user = DB::table('team_members')->where('id', $data[$field])->first();
                if ($user && $user->email) {
                    try {
                        Mail::to($user->email)->send(new ProjectAssigned(
                            $project->name, $projectId, $roleName, $assignerName
                        ));
                    } catch (\Throwable $e) {
                        \Log::warning("Email failed for {$user->email}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function sendAssignmentEmailsOnUpdate(int $projectId, array $newData, ?object $oldProject): void
    {
        if (!$oldProject) return;

        $project = $this->projectRepository->findById($projectId);
        $assignerName = auth()->user()->name;

        $checks = [
            'developer_id'       => 'Developer',
            'analyst_testing_id' => 'Analyst Testing',
            'analyst_id'         => 'Analyst',
        ];

        foreach ($checks as $field => $roleName) {
            if (isset($newData[$field]) && $newData[$field] != ($oldProject->$field ?? null)) {
                $user = DB::table('team_members')->where('id', $newData[$field])->first();
                if ($user && $user->email) {
                    try {
                        Mail::to($user->email)->send(new ProjectAssigned(
                            $project->name, $projectId, $roleName, $assignerName
                        ));
                    } catch (\Throwable $e) {
                        \Log::warning("Email failed for {$user->email}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function authRole(): string
    {
        $role = auth()->user()?->role;
        return $role instanceof \App\Enums\Role ? $role->value : (string) ($role ?? '');
    }
}
