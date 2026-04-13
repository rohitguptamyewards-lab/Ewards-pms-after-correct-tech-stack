<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateController extends Controller
{
    public function index(int $projectId): JsonResponse
    {
        $updates = DB::table('project_updates')
            ->leftJoin('team_members', 'project_updates.created_by', '=', 'team_members.id')
            ->select('project_updates.*', 'team_members.name as author_name')
            ->where('project_updates.project_id', $projectId)
            ->orderByDesc('project_updates.created_at')
            ->get();

        return response()->json($updates);
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string'],
            'source'  => ['sometimes', 'string'],
        ]);

        $id = DB::table('project_updates')->insertGetId([
            'project_id' => $projectId,
            'content'    => $data['content'],
            'source'     => $data['source'] ?? 'manual',
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        $update = DB::table('project_updates')
            ->leftJoin('team_members', 'project_updates.created_by', '=', 'team_members.id')
            ->select('project_updates.*', 'team_members.name as author_name')
            ->where('project_updates.id', $id)
            ->first();

        return response()->json($update, 201);
    }
}
