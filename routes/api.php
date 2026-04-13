<?php
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\PlannerController;
use App\Http\Controllers\Api\V1\StageController;
use App\Http\Controllers\Api\V1\UpdateController;
use App\Http\Controllers\Api\V1\WorkerController;
use App\Http\Controllers\Api\V1\TicketLinkController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\BlockerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\WorkLogController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\AutomationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->middleware(['auth'])->group(function () {
    // Projects
    Route::apiResource('projects', ProjectController::class)->only(['index', 'store', 'show', 'update']);

    // Project Children (subtasks)
    Route::get('projects/{id}/children', [ProjectController::class, 'children']);

    // Project Attachments
    Route::post('projects/{id}/attachments', [\App\Http\Controllers\Api\V1\ProjectController::class, 'uploadAttachment']);
    Route::delete('attachments/{id}', [\App\Http\Controllers\Api\V1\ProjectController::class, 'deleteAttachment']);

    // Planners
    Route::get('projects/{projectId}/planners', [PlannerController::class, 'index']);
    Route::post('planners', [PlannerController::class, 'store']);
    Route::put('planners/{id}', [PlannerController::class, 'update']);
    Route::delete('planners/{id}', [PlannerController::class, 'destroy']);
    Route::post('projects/{projectId}/planners/reorder', [PlannerController::class, 'reorder']);

    // Workers
    Route::get('projects/{projectId}/workers', [WorkerController::class, 'index']);
    Route::post('projects/{projectId}/workers/owner', [WorkerController::class, 'assignOwner']);
    Route::post('projects/{projectId}/workers/contributor', [WorkerController::class, 'addContributor']);
    Route::delete('projects/{projectId}/workers/{userId}', [WorkerController::class, 'removeContributor']);

    // Stages
    Route::get('projects/{projectId}/stage', [StageController::class, 'show']);
    Route::put('projects/{projectId}/stage', [StageController::class, 'update']);
    Route::get('projects/{projectId}/stage/history', [StageController::class, 'history']);

    // Updates
    Route::get('projects/{projectId}/updates', [UpdateController::class, 'index']);
    Route::post('projects/{projectId}/updates', [UpdateController::class, 'store']);

    // Ticket Links
    Route::get('projects/{projectId}/tickets', [TicketLinkController::class, 'index']);
    Route::post('projects/{projectId}/tickets', [TicketLinkController::class, 'store']);
    Route::delete('tickets/{id}', [TicketLinkController::class, 'destroy']);

    // Transfers
    Route::get('projects/{projectId}/transfers', [TransferController::class, 'index']);
    Route::post('projects/{projectId}/transfers', [TransferController::class, 'store']);

    // Blockers
    Route::get('projects/{projectId}/blockers', [BlockerController::class, 'index']);
    Route::post('projects/{projectId}/blockers', [BlockerController::class, 'store']);
    Route::put('blockers/{id}/resolve', [BlockerController::class, 'resolve']);

    // Dashboard
    Route::get('dashboard/manager', [DashboardController::class, 'manager']);
    Route::get('dashboard/employee', [DashboardController::class, 'employee']);
    Route::get('dashboard/activity-report', [DashboardController::class, 'activityReport']);

    // Reports
    Route::get('reports/projects', [ReportController::class, 'projects']);
    Route::get('reports/workers', [ReportController::class, 'workers']);
    Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('reports/projects/{projectId}/worklogs', [ReportController::class, 'projectWorklogs']);
    Route::get('reports/members/{memberId}/worklogs', [ReportController::class, 'memberWorklogs']);

    // Work Logs
    Route::get('work-logs', [WorkLogController::class, 'index']);
    Route::post('work-logs', [WorkLogController::class, 'store']);
    Route::put('work-logs/{id}', [WorkLogController::class, 'update']);
    Route::delete('work-logs/{id}', [WorkLogController::class, 'destroy']);

    // Team Members
    Route::put('team-members/{id}', [\App\Http\Controllers\Api\V1\TeamMemberController::class, 'update']);
    Route::post('team-members/{id}/toggle-active', [\App\Http\Controllers\Api\V1\TeamMemberController::class, 'toggleActive']);

    // Automations
    Route::get('automations', [AutomationController::class, 'index']);
    Route::post('automations', [AutomationController::class, 'store']);
    Route::put('automations/{id}', [AutomationController::class, 'update']);
    Route::delete('automations/{id}', [AutomationController::class, 'destroy']);
    Route::post('automations/{id}/toggle', [AutomationController::class, 'toggle']);
    Route::post('automations/{id}/run', [AutomationController::class, 'runNow']);
    Route::get('automations/{id}/logs', [AutomationController::class, 'logs']);

    // Release Notes
    Route::post('release-notes/setup', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'setupTables']);
    Route::get('projects/{projectId}/release-notes', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'index']);
    Route::post('projects/{projectId}/release-notes', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'store']);
    Route::put('release-notes/{id}', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'update']);
    Route::delete('release-notes/{id}', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'destroy']);
    Route::post('release-notes/{id}/files', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'uploadFiles']);
    Route::delete('release-note-files/{fileId}', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'deleteFile']);
    Route::post('release-notes/{id}/links', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'addLink']);
    Route::delete('release-note-links/{linkId}', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'deleteLink']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Dropdowns
    Route::get('team-members', function () {
        return response()->json(
            \Illuminate\Support\Facades\DB::table('team_members')
                ->select('id', 'name', 'email', 'role')
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    });
});
