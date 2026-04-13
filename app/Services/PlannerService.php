<?php
namespace App\Services;

use App\Repositories\PlannerRepository;
use Illuminate\Support\Facades\DB;

class PlannerService
{
    public function __construct(
        private readonly PlannerRepository $plannerRepository,
    ) {}

    public function create(array $data): int
    {
        // Fallback: if no assignee, use project owner
        if (empty($data['assigned_to'])) {
            $ownerId = DB::table('projects')->where('id', $data['project_id'])->value('owner_id');
            $data['assigned_to'] = $ownerId;
        }

        return $this->plannerRepository->create($data);
    }
}
