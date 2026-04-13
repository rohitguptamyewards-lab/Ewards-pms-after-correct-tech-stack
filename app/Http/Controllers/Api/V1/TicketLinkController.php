<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketLinkController extends Controller
{
    public function index(int $projectId): JsonResponse
    {
        $tickets = DB::table('project_ticket_links')
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($tickets);
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $data = $request->validate([
            'ticket_id'   => ['required', 'string', 'max:255'],
            'source_type' => ['sometimes', 'string'],
        ]);

        $id = DB::table('project_ticket_links')->insertGetId([
            'project_id'  => $projectId,
            'ticket_id'   => $data['ticket_id'],
            'source_type' => $data['source_type'] ?? 'external',
            'created_at'  => now(),
        ]);

        return response()->json(
            DB::table('project_ticket_links')->where('id', $id)->first(), 201
        );
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('project_ticket_links')->where('id', $id)->delete();
        return response()->json(['message' => 'Ticket link removed']);
    }
}
