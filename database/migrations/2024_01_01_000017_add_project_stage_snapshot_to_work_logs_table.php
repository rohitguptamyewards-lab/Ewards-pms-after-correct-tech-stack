<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const WORKLOG_CUSTOM_TASK_TYPE = 'worklog_custom_project';

    public function up(): void
    {
        if (!Schema::hasTable('work_logs') || Schema::hasColumn('work_logs', 'project_stage_snapshot')) {
            return;
        }

        Schema::table('work_logs', function (Blueprint $table) {
            $table->string('project_stage_snapshot')->nullable()->after('status');
        });

        $latestStageByProject = DB::table('project_stages')
            ->select('project_id', 'stage_name')
            ->orderByDesc('created_at')
            ->get()
            ->unique('project_id')
            ->pluck('stage_name', 'project_id');

        $projectsById = DB::table('projects')
            ->select('id', 'status', 'custom_task_type')
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        DB::table('work_logs')
            ->select('id', 'project_id')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($logs) use ($latestStageByProject, $projectsById) {
                foreach ($logs as $log) {
                    $project = $projectsById->get($log->project_id);

                    if (!$project || $project->custom_task_type === self::WORKLOG_CUSTOM_TASK_TYPE) {
                        continue;
                    }

                    $snapshot = $latestStageByProject->get($log->project_id) ?: $project->status;

                    DB::table('work_logs')
                        ->where('id', $log->id)
                        ->update([
                            'project_stage_snapshot' => $snapshot,
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('work_logs') || !Schema::hasColumn('work_logs', 'project_stage_snapshot')) {
            return;
        }

        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn('project_stage_snapshot');
        });
    }
};
