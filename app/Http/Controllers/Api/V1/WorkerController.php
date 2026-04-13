<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerController extends Controller
{
    public function index(int $projectId): JsonResponse
    {
        $workers = DB::table('project_workers')
            ->join('team_members', 'project_workers.user_id', '=', 'team_members.id')
            ->select('project_workers.*', 'team_members.name as user_name', 'team_members.email as user_email', 'team_members.role as user_role')
            ->where('project_workers.project_id', $projectId)
            ->orderByRaw("CASE WHEN project_workers.role = 'owner' THEN 0 ELSE 1 END")
            ->get();

        return response()->json($workers);
    }

    public function assignOwner(Request $request, int $projectId): JsonResponse
    {
        $role = auth()->user()?->role instanceof \App\Enums\Role ? auth()->user()->role->value : (string) (auth()->user()?->role ?? '');
        abort_unless(in_array($role, ['manager', 'analyst_head']), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:team_members,id'],
        ]);

        DB::transaction(function () use ($projectId, $data) {
            // Remove current owner role
            DB::table('project_workers')
                ->where('project_id', $projectId)
                ->where('role', 'owner')
                ->update(['role' => 'contributor']);

            // Check if user already on project
            $exists = DB::table('project_workers')
                ->where('project_id', $projectId)
                ->where('user_id', $data['user_id'])
                ->exists();

            if ($exists) {
                DB::table('project_workers')
                    ->where('project_id', $projectId)
                    ->where('user_id', $data['user_id'])
                    ->update(['role' => 'owner', 'assigned_by' => auth()->id(), 'assigned_at' => now()]);
            } else {
                DB::table('project_workers')->insert([
                    'project_id'  => $projectId,
                    'user_id'     => $data['user_id'],
                    'role'        => 'owner',
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                ]);
            }

            // Update projects table
            DB::table('projects')->where('id', $projectId)->update([
                'owner_id'   => $data['user_id'],
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Owner assigned']);
    }

    public function addContributor(Request $request, int $projectId): JsonResponse
    {
        $role = auth()->user()?->role instanceof \App\Enums\Role ? auth()->user()->role->value : (string) (auth()->user()?->role ?? '');
        abort_unless(in_array($role, ['manager', 'analyst_head']), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:team_members,id'],
        ]);

        $exists = DB::table('project_workers')
            ->where('project_id', $projectId)
            ->where('user_id', $data['user_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'User already on project'], 422);
        }

        DB::table('project_workers')->insert([
            'project_id'  => $projectId,
            'user_id'     => $data['user_id'],
            'role'        => 'contributor',
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
        ]);

        return response()->json(['message' => 'Contributor added'], 201);
    }

    public function removeContributor(int $projectId, int $userId): JsonResponse
    {
        $role = auth()->user()?->role instanceof \App\Enums\Role ? auth()->user()->role->value : (string) (auth()->user()?->role ?? '');
        abort_unless(in_array($role, ['manager', 'analyst_head']), 403);

        $worker = DB::table('project_workers')
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->first();

        if (! $worker) {
            return response()->json(['message' => 'Worker not found'], 404);
        }

        if ($worker->role === 'owner') {
            return response()->json(['message' => 'Cannot remove owner. Transfer ownership first.'], 422);
        }

        DB::table('project_workers')
            ->where('project_id', $projectId)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['message' => 'Contributor removed']);
    }
}
