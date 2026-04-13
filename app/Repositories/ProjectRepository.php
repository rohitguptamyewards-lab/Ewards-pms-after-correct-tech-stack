<?php
namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class ProjectRepository
{
    private const WORKLOG_CUSTOM_TASK_TYPE = 'worklog_custom_project';

    private function baseSelect(): array
    {
        return [
            'projects.*',
            'owner.name as owner_name',
            'analyst.name as analyst_name',
            'tester.name as analyst_testing_name',
            'dev.name as developer_name',
            DB::raw('(SELECT COUNT(*) FROM project_planners WHERE project_planners.project_id = projects.id) as planner_count'),
            DB::raw('(SELECT COUNT(*) FROM project_planners WHERE project_planners.project_id = projects.id AND project_planners.status = \'done\') as planner_done_count'),
            DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage'),
            DB::raw('(SELECT COUNT(*) FROM projects AS children WHERE children.parent_id = projects.id AND children.deleted_at IS NULL) as children_count'),
            DB::raw('(SELECT content FROM project_updates WHERE project_updates.project_id = projects.id ORDER BY project_updates.created_at DESC LIMIT 1) as last_comment'),
            DB::raw('(SELECT created_at FROM project_updates WHERE project_updates.project_id = projects.id ORDER BY project_updates.created_at DESC LIMIT 1) as last_comment_at'),
            DB::raw('(SELECT COUNT(*) FROM project_updates WHERE project_updates.project_id = projects.id) as comment_count'),
        ];
    }

    private function baseJoins($query): void
    {
        $query->leftJoin('team_members as owner', 'projects.owner_id', '=', 'owner.id')
              ->leftJoin('team_members as analyst', 'projects.analyst_id', '=', 'analyst.id')
              ->leftJoin('team_members as tester', 'projects.analyst_testing_id', '=', 'tester.id')
              ->leftJoin('team_members as dev', 'projects.developer_id', '=', 'dev.id');
    }

    public function findAll(array $filters = [], int $perPage = 20)
    {
        $query = DB::table('projects');
        $this->baseJoins($query);
        $query->leftJoin('team_members as creator', 'projects.created_by', '=', 'creator.id');

        $select = $this->baseSelect();
        $select[] = 'creator.name as created_by_name';
        $query->select($select);

        $query->whereNull('projects.deleted_at');

        // Only show top-level projects (not subtasks) unless fetching children
        if (empty($filters['parent_id'])) {
            $query->whereNull('projects.parent_id');
        } else {
            $query->where('projects.parent_id', $filters['parent_id']);
        }

        $this->applyProjectScope($query, $filters);

        if (!empty($filters['status'])) {
            $query->where('projects.status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $query->where('projects.priority', $filters['priority']);
        }
        if (!empty($filters['owner_id'])) {
            $query->where('projects.owner_id', $filters['owner_id']);
        }
        if (!empty($filters['work_type'])) {
            $query->where('projects.work_type', $filters['work_type']);
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($s) {
                $q->where('projects.name', 'like', $s)
                  ->orWhere('projects.description', 'like', $s);
            });
        }

        return $query->orderByDesc('projects.created_at')->paginate($perPage);
    }

    public function findByWorker(int $userId, array $filters = [], int $perPage = 20)
    {
        $query = DB::table('projects');
        $this->baseJoins($query);

        $query->select($this->baseSelect())
              ->whereNull('projects.deleted_at')
              ->where(function ($q) use ($userId) {
                  $q->where('projects.owner_id', $userId)
                    ->orWhere('projects.analyst_id', $userId)
                    ->orWhere('projects.analyst_testing_id', $userId)
                    ->orWhere('projects.developer_id', $userId)
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('project_workers')
                            ->whereColumn('project_workers.project_id', 'projects.id')
                            ->where('project_workers.user_id', $userId);
                    });
              });

        // Only show top-level projects unless fetching children
        if (empty($filters['parent_id'])) {
            $query->whereNull('projects.parent_id');
        } else {
            $query->where('projects.parent_id', $filters['parent_id']);
        }

        $this->applyProjectScope($query, $filters);

        if (!empty($filters['status'])) {
            $query->where('projects.status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($s) {
                $q->where('projects.name', 'like', $s)
                  ->orWhere('projects.description', 'like', $s);
            });
        }

        return $query->orderByDesc('projects.created_at')->paginate($perPage);
    }

    /**
     * Fetch children (subtasks) of a given project.
     */
    public function findChildren(int $parentId): \Illuminate\Support\Collection
    {
        $query = DB::table('projects');
        $this->baseJoins($query);

        return $query->select($this->baseSelect())
            ->whereNull('projects.deleted_at')
            ->where('projects.parent_id', $parentId)
            ->orderBy('projects.created_at')
            ->get();
    }

    /**
     * Get the nesting depth of a project (0 = top-level).
     */
    public function getDepth(int $projectId): int
    {
        $depth = 0;
        $current = DB::table('projects')->select('parent_id')->where('id', $projectId)->first();

        while ($current && $current->parent_id) {
            $depth++;
            $current = DB::table('projects')->select('parent_id')->where('id', $current->parent_id)->first();
        }

        return $depth;
    }

    public function findById(int $id): ?stdClass
    {
        return DB::table('projects')
            ->leftJoin('team_members as owner', 'projects.owner_id', '=', 'owner.id')
            ->leftJoin('team_members as creator', 'projects.created_by', '=', 'creator.id')
            ->leftJoin('team_members as analyst', 'projects.analyst_id', '=', 'analyst.id')
            ->leftJoin('team_members as tester', 'projects.analyst_testing_id', '=', 'tester.id')
            ->leftJoin('team_members as dev', 'projects.developer_id', '=', 'dev.id')
            ->select(
                'projects.*',
                'owner.name as owner_name',
                'creator.name as created_by_name',
                'analyst.name as analyst_name',
                'tester.name as analyst_testing_name',
                'dev.name as developer_name',
                DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage')
            )
            ->where('projects.id', $id)
            ->whereNull('projects.deleted_at')
            ->first();
    }

    public function create(array $data): int
    {
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }
        if (isset($data['linked_project_ids']) && is_array($data['linked_project_ids'])) {
            $data['linked_project_ids'] = json_encode($data['linked_project_ids']);
        }
        $data['created_at'] = now();
        $data['updated_at'] = now();
        return DB::table('projects')->insertGetId($data);
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }
        if (isset($data['linked_project_ids']) && is_array($data['linked_project_ids'])) {
            $data['linked_project_ids'] = json_encode($data['linked_project_ids']);
        }
        $data['updated_at'] = now();
        return DB::table('projects')->where('id', $id)->update($data) > 0;
    }

    private function applyProjectScope($query, array $filters): void
    {
        $scope = $filters['project_scope'] ?? 'default';

        if ($scope === 'worklog_custom') {
            $query->where('projects.custom_task_type', self::WORKLOG_CUSTOM_TASK_TYPE);
            return;
        }

        // Default projects section excludes worklog-custom projects.
        $query->where(function ($q) {
            $q->whereNull('projects.custom_task_type')
              ->orWhere('projects.custom_task_type', '!=', self::WORKLOG_CUSTOM_TASK_TYPE);
        });
    }
}
