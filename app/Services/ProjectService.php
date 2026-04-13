<?php
namespace App\Services;

use App\Repositories\ProjectRepository;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
    ) {}

    public function getProjectWithDetails(int $id): ?array
    {
        $project = $this->projectRepository->findById($id);

        if (! $project) {
            return null;
        }

        $planners = DB::table('project_planners')
            ->leftJoin('team_members', 'project_planners.assigned_to', '=', 'team_members.id')
            ->select('project_planners.*', 'team_members.name as assignee_name')
            ->where('project_planners.project_id', $id)
            ->orderBy('project_planners.order_index')
            ->get()->toArray();

        $workers = DB::table('project_workers')
            ->join('team_members', 'project_workers.user_id', '=', 'team_members.id')
            ->select('project_workers.*', 'team_members.name as user_name', 'team_members.email as user_email', 'team_members.role as user_role')
            ->where('project_workers.project_id', $id)
            ->orderByRaw("CASE WHEN project_workers.role = 'owner' THEN 0 ELSE 1 END")
            ->get()->toArray();

        $updates = DB::table('project_updates')
            ->leftJoin('team_members', 'project_updates.created_by', '=', 'team_members.id')
            ->select('project_updates.*', 'team_members.name as author_name')
            ->where('project_updates.project_id', $id)
            ->orderByDesc('project_updates.created_at')
            ->get()->toArray();

        $blockers = DB::table('project_blockers')
            ->leftJoin('team_members as creator', 'project_blockers.created_by', '=', 'creator.id')
            ->leftJoin('team_members as resolver', 'project_blockers.resolved_by', '=', 'resolver.id')
            ->select('project_blockers.*', 'creator.name as creator_name', 'resolver.name as resolver_name')
            ->where('project_blockers.project_id', $id)
            ->orderByDesc('project_blockers.created_at')
            ->get()->toArray();

        $tickets = DB::table('project_ticket_links')
            ->where('project_id', $id)
            ->orderByDesc('created_at')
            ->get()->toArray();

        $stageHistory = DB::table('project_stages')
            ->leftJoin('team_members', 'project_stages.updated_by', '=', 'team_members.id')
            ->select('project_stages.*', 'team_members.name as updater_name')
            ->where('project_stages.project_id', $id)
            ->orderByDesc('project_stages.created_at')
            ->get()->toArray();

        $transfers = DB::table('project_transfers')
            ->leftJoin('team_members as from_u', 'project_transfers.from_user', '=', 'from_u.id')
            ->leftJoin('team_members as to_u', 'project_transfers.to_user', '=', 'to_u.id')
            ->select('project_transfers.*', 'from_u.name as from_name', 'to_u.name as to_name')
            ->where('project_transfers.project_id', $id)
            ->orderByDesc('project_transfers.created_at')
            ->get()->toArray();

        return [
            'project'      => $project,
            'planners'     => $planners,
            'workers'      => $workers,
            'updates'      => $updates,
            'blockers'     => $blockers,
            'tickets'      => $tickets,
            'stageHistory' => $stageHistory,
            'transfers'    => $transfers,
        ];
    }
}
