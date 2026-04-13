<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function index(int $projectId): JsonResponse
    {
        $transfers = DB::table('project_transfers')
            ->leftJoin('team_members as from_u', 'project_transfers.from_user', '=', 'from_u.id')
            ->leftJoin('team_members as to_u', 'project_transfers.to_user', '=', 'to_u.id')
            ->select('project_transfers.*', 'from_u.name as from_name', 'to_u.name as to_name')
            ->where('project_transfers.project_id', $projectId)
            ->orderByDesc('project_transfers.created_at')
            ->get();

        return response()->json($transfers);
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $role = auth()->user()->role->value ?? auth()->user()->role;
        abort_unless(in_array($role, ['manager', 'analyst_head', 'analyst']), 403);

        $data = $request->validate([
            'to_user' => ['required', 'integer', 'exists:team_members,id'],
            'notes'   => ['required', 'string', 'min:3'],
        ]);

        $currentOwner = DB::table('project_workers')
            ->where('project_id', $projectId)
            ->where('role', 'owner')
            ->first();

        if (! $currentOwner) {
            return response()->json(['message' => 'No current owner found'], 422);
        }

        DB::transaction(function () use ($projectId, $data, $currentOwner) {
            // Log transfer
            DB::table('project_transfers')->insert([
                'project_id' => $projectId,
                'from_user'  => $currentOwner->user_id,
                'to_user'    => $data['to_user'],
                'notes'      => $data['notes'],
                'created_at' => now(),
            ]);

            // Demote current owner
            DB::table('project_workers')
                ->where('project_id', $projectId)
                ->where('role', 'owner')
                ->update(['role' => 'contributor']);

            // Promote new owner (or add if not on project)
            $exists = DB::table('project_workers')
                ->where('project_id', $projectId)
                ->where('user_id', $data['to_user'])
                ->exists();

            if ($exists) {
                DB::table('project_workers')
                    ->where('project_id', $projectId)
                    ->where('user_id', $data['to_user'])
                    ->update(['role' => 'owner', 'assigned_by' => auth()->id(), 'assigned_at' => now()]);
            } else {
                DB::table('project_workers')->insert([
                    'project_id'  => $projectId,
                    'user_id'     => $data['to_user'],
                    'role'        => 'owner',
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                ]);
            }

            // Update projects table
            DB::table('projects')->where('id', $projectId)->update([
                'owner_id'   => $data['to_user'],
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Ownership transferred'], 201);
    }
}
