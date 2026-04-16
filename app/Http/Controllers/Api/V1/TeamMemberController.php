<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    private const ADMIN_ROLES = ['manager', 'analyst_head'];
    private const MANAGER_ROLES = ['manager', 'analyst_head', 'analyst'];
    private const ALL_ROLES = ['manager', 'analyst_head', 'analyst', 'senior_developer', 'developer', 'employee'];
    private const EMPLOYEE_TYPES = ['technical', 'non_technical'];

    public function index(Request $request)
    {
        $role = $this->authRole();
        abort_unless(in_array($role, self::MANAGER_ROLES), 403);

        $filters = $request->only(['search', 'role', 'employee_type', 'is_active']);

        $query = DB::table('team_members')
            ->leftJoin('team_members as managers', 'team_members.manager_id', '=', 'managers.id')
            ->select(
                'team_members.*',
                'managers.name as manager_name',
                DB::raw('(SELECT COUNT(DISTINCT pw.project_id) FROM project_workers pw WHERE pw.user_id = team_members.id) as project_count'),
                DB::raw('(SELECT COALESCE(SUM(wl.hours_spent), 0) FROM work_logs wl WHERE wl.user_id = team_members.id AND wl.deleted_at IS NULL) as total_hours')
            )
            ->whereNull('team_members.deleted_at');

        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($s) {
                $q->where('team_members.name', 'like', $s)
                  ->orWhere('team_members.email', 'like', $s);
            });
        }
        if (!empty($filters['role'])) {
            $query->where('team_members.role', $filters['role']);
        }
        if (!empty($filters['employee_type'])) {
            $query->where('team_members.employee_type', $filters['employee_type']);
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('team_members.is_active', (bool) $filters['is_active']);
        }

        $members = $query->orderBy('team_members.name')->get();

        if ($request->wantsJson()) {
            return response()->json($members);
        }

        return Inertia::render('TeamMembers/Index', [
            'members'  => $members,
            'filters'  => $filters,
            'managers' => $this->managerOptions(),
        ]);
    }

    public function create()
    {
        abort_unless(in_array($this->authRole(), self::ADMIN_ROLES), 403);

        return Inertia::render('TeamMembers/Create', [
            'managers' => $this->managerOptions(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(in_array($this->authRole(), self::ADMIN_ROLES), 403);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:team_members,email'],
            'password'      => ['required', 'string', 'min:6'],
            'role'          => ['required', 'string', 'in:' . implode(',', self::ALL_ROLES)],
            'employee_type' => ['required', 'string', Rule::in(self::EMPLOYEE_TYPES)],
            'manager_id'    => [
                'nullable',
                'integer',
                Rule::exists('team_members', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at')
                        ->where('is_active', true)
                        ->whereIn('role', self::MANAGER_ROLES);
                }),
            ],
            'joined_date'   => ['nullable', 'date'],
        ]);

        $plainPassword = $data['password'];
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = true;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('team_members')->insertGetId($data);

        // Send welcome email
        try {
            $roleLabel = str_replace('_', ' ', ucwords($data['role'], '_'));
            Mail::to($data['email'])->send(new WelcomeMember(
                $data['name'],
                $data['email'],
                $plainPassword,
                $roleLabel,
                auth()->user()->name,
            ));
        } catch (\Throwable $e) {
            \Log::warning("Welcome email failed for {$data['email']}: " . $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(DB::table('team_members')->find($id), 201);
        }

        return redirect()->route('team-members.index')
            ->with('success', 'Team member created successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(in_array($this->authRole(), self::ADMIN_ROLES), 403);

        $member = DB::table('team_members')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$member) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'email'         => ['sometimes', 'email', 'max:255', 'unique:team_members,email,' . $id],
            'role'          => ['sometimes', 'string', 'in:' . implode(',', self::ALL_ROLES)],
            'employee_type' => ['sometimes', 'string', Rule::in(self::EMPLOYEE_TYPES)],
            'manager_id'    => [
                'nullable',
                'integer',
                Rule::exists('team_members', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at')
                        ->where('is_active', true)
                        ->whereIn('role', self::MANAGER_ROLES);
                }),
            ],
            'is_active'     => ['sometimes', 'boolean'],
            'password'      => ['sometimes', 'string', 'min:6'],
            'joined_date'   => ['nullable', 'date'],
        ]);

        if (array_key_exists('manager_id', $data) && $data['manager_id'] !== null && (int) $data['manager_id'] === $id) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => [
                    'manager_id' => ['A team member cannot report to themselves.'],
                ],
            ], 422);
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $data['updated_at'] = now();

        DB::table('team_members')->where('id', $id)->update($data);

        return response()->json(DB::table('team_members')->find($id));
    }

    public function toggleActive(int $id): JsonResponse
    {
        abort_unless(in_array($this->authRole(), self::ADMIN_ROLES), 403);

        $member = DB::table('team_members')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$member) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::table('team_members')->where('id', $id)->update([
            'is_active'  => !$member->is_active,
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('team_members')->find($id));
    }

    private function authRole(): string
    {
        $role = auth()->user()?->role;
        return $role instanceof \App\Enums\Role ? $role->value : (string) ($role ?? '');
    }

    private function managerOptions()
    {
        return DB::table('team_members')
            ->select('id', 'name', 'email', 'role')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereIn('role', self::MANAGER_ROLES)
            ->orderBy('name')
            ->get();
    }
}
