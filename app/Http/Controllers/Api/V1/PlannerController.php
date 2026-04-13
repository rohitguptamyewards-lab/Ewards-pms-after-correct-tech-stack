<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlannerRequest;
use App\Repositories\PlannerRepository;
use App\Services\PlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlannerController extends Controller
{
    public function __construct(
        private readonly PlannerService $plannerService,
        private readonly PlannerRepository $plannerRepository,
    ) {}

    public function index(int $projectId): JsonResponse
    {
        return response()->json(
            $this->plannerRepository->findByProject($projectId)
        );
    }

    public function store(StorePlannerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $id = $this->plannerService->create($data);

        return response()->json(
            $this->plannerRepository->findById($id), 201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title'          => ['sometimes', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'milestone_flag' => ['sometimes', 'boolean'],
            'assigned_to'    => ['nullable', 'integer', 'exists:team_members,id'],
            'due_date'       => ['nullable', 'date'],
            'status'         => ['sometimes', 'string'],
        ]);

        $this->plannerRepository->update($id, $data);

        return response()->json(
            $this->plannerRepository->findById($id)
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->plannerRepository->delete($id);
        return response()->json(['message' => 'Deleted']);
    }

    public function reorder(Request $request, int $projectId): JsonResponse
    {
        $data = $request->validate([
            'ordered_ids'   => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $this->plannerRepository->reorder($projectId, $data['ordered_ids']);

        return response()->json(['message' => 'Reordered']);
    }
}
