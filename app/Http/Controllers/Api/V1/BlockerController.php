<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlockerController extends Controller
{
    public function index(int $projectId): JsonResponse
    {
        $blockers = DB::table('project_blockers')
            ->leftJoin('team_members as creator', 'project_blockers.created_by', '=', 'creator.id')
            ->leftJoin('team_members as resolver', 'project_blockers.resolved_by', '=', 'resolver.id')
            ->select('project_blockers.*', 'creator.name as creator_name', 'resolver.name as resolver_name')
            ->where('project_blockers.project_id', $projectId)
            ->orderByDesc('project_blockers.created_at')
            ->get();

        return response()->json($blockers);
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string'],
        ]);

        $id = DB::table('project_blockers')->insertGetId([
            'project_id'  => $projectId,
            'description' => $data['description'],
            'status'      => 'active',
            'created_by'  => auth()->id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $blocker = DB::table('project_blockers')
            ->leftJoin('team_members as creator', 'project_blockers.created_by', '=', 'creator.id')
            ->select('project_blockers.*', 'creator.name as creator_name')
            ->where('project_blockers.id', $id)
            ->first();

        // In-app notification to project owner
        $project = DB::table('projects')->where('id', $projectId)->first();
        if ($project) {
            $notifyIds = array_filter([
                $project->owner_id, $project->analyst_id,
            ], fn($uid) => $uid && $uid != auth()->id());
            if ($notifyIds) {
                NotificationController::notifyMany(
                    $notifyIds,
                    'blocker',
                    "New blocker: {$project->name}",
                    auth()->user()->name . " reported a blocker: " . \Illuminate\Support\Str::limit($data['description'], 80),
                    ['project_id' => $projectId, 'link' => "/projects/{$projectId}"]
                );
            }
        }

        return response()->json($blocker, 201);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        DB::table('project_blockers')
            ->where('id', $id)
            ->update([
                'status'      => 'resolved',
                'resolved_by' => auth()->id(),
                'updated_at'  => now(),
            ]);

        return response()->json(['message' => 'Blocker resolved']);
    }
}
