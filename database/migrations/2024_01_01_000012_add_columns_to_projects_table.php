<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                // Columns from base migration that may be missing on pre-existing tables
                if (!Schema::hasColumn('projects', 'objective')) {
                    $table->text('objective')->nullable();
                }
                if (!Schema::hasColumn('projects', 'tags')) {
                    $table->json('tags')->nullable();
                }
                if (!Schema::hasColumn('projects', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!Schema::hasColumn('projects', 'linked_project_ids')) {
                    $table->json('linked_project_ids')->nullable();
                }
                if (!Schema::hasColumn('projects', 'custom_task_type')) {
                    $table->string('custom_task_type')->nullable();
                }
                // Additional columns
                if (!Schema::hasColumn('projects', 'work_type')) {
                    $table->string('work_type')->nullable();
                }
                if (!Schema::hasColumn('projects', 'task_type')) {
                    $table->string('task_type')->nullable();
                }
                if (!Schema::hasColumn('projects', 'ticket_link')) {
                    $table->text('ticket_link')->nullable();
                }
                if (!Schema::hasColumn('projects', 'analyst_id')) {
                    $table->unsignedBigInteger('analyst_id')->nullable();
                }
                if (!Schema::hasColumn('projects', 'analyst_testing_id')) {
                    $table->unsignedBigInteger('analyst_testing_id')->nullable();
                }
                if (!Schema::hasColumn('projects', 'developer_id')) {
                    $table->unsignedBigInteger('developer_id')->nullable();
                }
                if (!Schema::hasColumn('projects', 'document_link')) {
                    $table->text('document_link')->nullable();
                }
                if (!Schema::hasColumn('projects', 'ai_chat_link')) {
                    $table->text('ai_chat_link')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn([
                    'work_type', 'task_type', 'ticket_link',
                    'analyst_id', 'analyst_testing_id', 'developer_id',
                    'document_link', 'ai_chat_link',
                ]);
            });
        }
    }
};
