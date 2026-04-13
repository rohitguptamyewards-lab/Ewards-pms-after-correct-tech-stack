<?php
namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class PlannerRepository
{
    public function findByProject(int $projectId): array
    {
        return DB::table('project_planners')
            ->leftJoin('team_members', 'project_planners.assigned_to', '=', 'team_members.id')
            ->select('project_planners.*', 'team_members.name as assignee_name')
            ->where('project_planners.project_id', $projectId)
            ->orderBy('project_planners.order_index')
            ->orderByDesc('project_planners.created_at')
            ->get()
            ->toArray();
    }

    public function findById(int $id): ?stdClass
    {
        return DB::table('project_planners')
            ->leftJoin('team_members', 'project_planners.assigned_to', '=', 'team_members.id')
            ->select('project_planners.*', 'team_members.name as assignee_name')
            ->where('project_planners.id', $id)
            ->first();
    }

    public function create(array $data): int
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        if (! isset($data['order_index'])) {
            $max = DB::table('project_planners')
                ->where('project_id', $data['project_id'])
                ->max('order_index');
            $data['order_index'] = ($max ?? 0) + 1;
        }

        return DB::table('project_planners')->insertGetId($data);
    }

    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = now();
        return DB::table('project_planners')->where('id', $id)->update($data) > 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('project_planners')->where('id', $id)->delete() > 0;
    }

    public function reorder(int $projectId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            DB::table('project_planners')
                ->where('id', $id)
                ->where('project_id', $projectId)
                ->update(['order_index' => $index + 1, 'updated_at' => now()]);
        }
    }
}
