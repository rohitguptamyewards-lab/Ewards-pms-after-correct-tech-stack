<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AutomationController extends Controller
{
    public function __construct(private readonly AutomationService $automationService) {}

    private function authorizeManager(): void
    {
        abort_unless(
            in_array($this->authRole(), ['manager', 'analyst_head']),
            403,
            'Only managers and analyst heads can manage automations.'
        );
    }

    public function index(Request $request)
    {
        $this->authorizeManager();

        $automations = DB::table('automations')
            ->leftJoin('team_members', 'automations.created_by', '=', 'team_members.id')
            ->select('automations.*', 'team_members.name as created_by_name')
            ->orderByDesc('automations.created_at')
            ->get()
            ->map(function ($a) {
                $a->trigger_config = json_decode($a->trigger_config, true);
                $a->action_config = json_decode($a->action_config, true);

                // Get last 5 logs
                $a->recent_logs = DB::table('automation_logs')
                    ->where('automation_id', $a->id)
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get()
                    ->map(function ($log) {
                        $log->details = json_decode($log->details, true);
                        return $log;
                    });

                return $a;
            });

        $teamMembers = DB::table('team_members')
            ->select('id', 'name', 'role')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json($automations);
        }

        return Inertia::render('Automations/Index', [
            'automations' => $automations,
            'teamMembers' => $teamMembers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManager();

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'trigger_type'   => ['required', 'string', 'in:schedule,stage_change,status_change,blocker_created'],
            'trigger_config' => ['required', 'array'],
            'action_type'    => ['required', 'string', 'in:send_email,send_notification'],
            'action_config'  => ['required', 'array'],
        ]);

        $id = DB::table('automations')->insertGetId([
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'is_active'      => true,
            'trigger_type'   => $data['trigger_type'],
            'trigger_config' => json_encode($data['trigger_config']),
            'action_type'    => $data['action_type'],
            'action_config'  => json_encode($data['action_config']),
            'created_by'     => auth()->id(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return response()->json(DB::table('automations')->where('id', $id)->first(), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeManager();

        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'is_active'      => ['sometimes', 'boolean'],
            'trigger_type'   => ['sometimes', 'string', 'in:schedule,stage_change,status_change,blocker_created'],
            'trigger_config' => ['sometimes', 'array'],
            'action_type'    => ['sometimes', 'string', 'in:send_email,send_notification'],
            'action_config'  => ['sometimes', 'array'],
        ]);

        $update = ['updated_at' => now()];
        foreach (['name', 'description', 'is_active', 'trigger_type', 'action_type'] as $field) {
            if (array_key_exists($field, $data)) $update[$field] = $data[$field];
        }
        if (isset($data['trigger_config'])) $update['trigger_config'] = json_encode($data['trigger_config']);
        if (isset($data['action_config'])) $update['action_config'] = json_encode($data['action_config']);

        DB::table('automations')->where('id', $id)->update($update);

        return response()->json(DB::table('automations')->where('id', $id)->first());
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorizeManager();

        $automation = DB::table('automations')->where('id', $id)->first();
        if (!$automation) return response()->json(['message' => 'Not found'], 404);

        DB::table('automations')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function toggle(int $id): JsonResponse
    {
        $this->authorizeManager();

        $automation = DB::table('automations')->where('id', $id)->first();
        if (!$automation) return response()->json(['message' => 'Not found'], 404);

        DB::table('automations')->where('id', $id)->update([
            'is_active'  => !$automation->is_active,
            'updated_at' => now(),
        ]);

        return response()->json(['is_active' => !$automation->is_active]);
    }

    public function runNow(int $id): JsonResponse
    {
        $this->authorizeManager();

        $automation = DB::table('automations')->where('id', $id)->first();
        if (!$automation) return response()->json(['message' => 'Not found'], 404);

        if ($automation->trigger_type === 'schedule') {
            // Force run by clearing last_run_at so the scheduler picks it up
            DB::table('automations')->where('id', $id)->update(['last_run_at' => null, 'updated_at' => now()]);

            try {
                $this->automationService->processScheduledAutomations();
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Run failed: ' . $e->getMessage()], 500);
            }

            return response()->json(['message' => 'Automation executed successfully.']);
        }

        return response()->json(['message' => 'Event-based automations run automatically on trigger.']);
    }

    public function logs(int $id): JsonResponse
    {
        $logs = DB::table('automation_logs')
            ->where('automation_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($log) {
                $log->details = json_decode($log->details, true);
                return $log;
            });

        return response()->json($logs);
    }

    private function getMatchedProjects(array $config): \Illuminate\Support\Collection
    {
        // Quick preview of what projects match the condition
        $condition = $config['condition'] ?? 'stage_is_live';
        $lookbackDays = $config['lookback_days'] ?? 7;
        $since = now()->subDays($lookbackDays);

        $query = DB::table('projects')
            ->select('projects.id', 'projects.name')
            ->whereNull('projects.deleted_at')
            ->where(function ($q) {
                $q->whereNull('projects.custom_task_type')
                  ->orWhere('projects.custom_task_type', '!=', 'worklog_custom_project');
            });

        if ($condition === 'stage_is_live') {
            $query->whereExists(function ($sub) use ($since) {
                $sub->select(DB::raw(1))
                    ->from('project_stages')
                    ->whereColumn('project_stages.project_id', 'projects.id')
                    ->where('project_stages.stage_name', 'live')
                    ->where('project_stages.created_at', '>=', $since);
            });
        }

        return $query->get();
    }

    private function authRole(): string
    {
        $role = auth()->user()?->role;
        return $role instanceof \App\Enums\Role ? $role->value : (string) ($role ?? '');
    }
}
