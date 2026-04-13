<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\StageChanged;
use App\Services\AutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class StageController extends Controller
{
    public function show(int $projectId): JsonResponse
    {
        $stage = DB::table('project_stages')
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->first();

        return response()->json($stage);
    }

    public function update(Request $request, int $projectId): JsonResponse
    {
        $data = $request->validate([
            'stage_name' => ['required', 'string'],
        ]);

        // Get old stage for logging
        $oldStage = DB::table('project_stages')
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->value('stage_name');

        // Insert new stage
        DB::table('project_stages')->insert([
            'project_id' => $projectId,
            'stage_name' => $data['stage_name'],
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Auto-log stage change to project_updates (comments)
        $userName = auth()->user()?->name ?? 'System';
        $oldLabel = $oldStage ? str_replace('_', ' ', ucwords($oldStage, '_')) : 'None';
        $newLabel = str_replace('_', ' ', ucwords($data['stage_name'], '_'));

        DB::table('project_updates')->insert([
            'project_id' => $projectId,
            'content'    => "Status changed from \"{$oldLabel}\" to \"{$newLabel}\" by {$userName}",
            'source'     => 'system',
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        // Send email + in-app notifications based on new stage
        $project = DB::table('projects')->where('id', $projectId)->first();
        if ($project) {
            $this->notifyOnStageChange($project, $data['stage_name'], $userName);
            $this->sendInAppStageNotifications($project, $data['stage_name'], $userName);

            // Trigger automation engine for stage changes
            try {
                app(AutomationService::class)->processStageChange($projectId, $oldStage, $data['stage_name']);
            } catch (\Throwable $e) {
                \Log::warning("Automation stage trigger failed: " . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Stage updated']);
    }

    public function history(int $projectId): JsonResponse
    {
        $history = DB::table('project_stages')
            ->leftJoin('team_members', 'project_stages.updated_by', '=', 'team_members.id')
            ->select('project_stages.*', 'team_members.name as updater_name')
            ->where('project_stages.project_id', $projectId)
            ->orderByDesc('project_stages.created_at')
            ->get();

        return response()->json($history);
    }

    private function sendInAppStageNotifications(object $project, string $newStage, string $changedBy): void
    {
        $stageLabel = str_replace('_', ' ', ucwords($newStage, '_'));
        $notifyIds = [];

        // Collect all assigned users on the project (excluding the person who made the change)
        foreach (['owner_id', 'analyst_id', 'developer_id', 'analyst_testing_id'] as $field) {
            if (!empty($project->{$field}) && $project->{$field} != auth()->id()) {
                $notifyIds[] = $project->{$field};
            }
        }

        if ($newStage === 'live') {
            $adminIds = DB::table('team_members')
                ->whereIn('role', ['manager', 'analyst_head', 'senior_developer'])
                ->whereNull('deleted_at')->where('is_active', true)
                ->pluck('id')->toArray();
            $notifyIds = array_merge($notifyIds, $adminIds);
        }

        $notifyIds = array_unique(array_filter($notifyIds));
        if ($notifyIds) {
            NotificationController::notifyMany(
                $notifyIds,
                'stage_change',
                "Stage updated: {$project->name}",
                "{$changedBy} moved \"{$project->name}\" to \"{$stageLabel}\"",
                ['project_id' => $project->id, 'link' => "/projects/{$project->id}"]
            );
        }
    }

    private function notifyOnStageChange(object $project, string $newStage, string $changedBy): void
    {
        $testingStages = [
            'testing_yet_to_start', 'testing_wip', 'ready_for_internal',
            'testing_wip_internal', 'bugs_reported_test_internal',
            'bugs_reported_testing', 'bugs_reported_internal',
            'live_testing_yet_to_start', 'live_testing_wip',
        ];

        $devStages = [
            'dev_yet_to_start', 'development_wip', 'dev_testing_wip',
            'dev_changes_required', 'bug_fixing_wip',
        ];

        try {
            // Notify analyst_testing person when moved to testing stage
            if (in_array($newStage, $testingStages) && $project->analyst_testing_id) {
                $tester = DB::table('team_members')->where('id', $project->analyst_testing_id)->first();
                if ($tester && $tester->email) {
                    Mail::to($tester->email)->send(new StageChanged(
                        $project->name, $project->id, $newStage, $changedBy
                    ));
                }
            }

            // Notify developer when moved to dev stage
            if (in_array($newStage, $devStages) && $project->developer_id) {
                $dev = DB::table('team_members')->where('id', $project->developer_id)->first();
                if ($dev && $dev->email) {
                    Mail::to($dev->email)->send(new StageChanged(
                        $project->name, $project->id, $newStage, $changedBy
                    ));
                }
            }

            // When project goes LIVE: notify all managers, analyst_heads, and senior_developers
            if ($newStage === 'live') {
                $admins = DB::table('team_members')
                    ->whereIn('role', ['manager', 'analyst_head', 'senior_developer'])
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->get();

                foreach ($admins as $admin) {
                    if ($admin->email) {
                        Mail::to($admin->email)->send(new StageChanged(
                            $project->name, $project->id, $newStage, $changedBy
                        ));
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning("Stage change email failed: " . $e->getMessage());
        }
    }
}
