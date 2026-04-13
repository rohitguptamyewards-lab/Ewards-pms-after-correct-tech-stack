<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('project_attachments')) {
            Schema::create('project_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('original_name');
                $table->string('stored_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }

        // Add custom_task_type column to projects
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'custom_task_type')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('custom_task_type')->nullable()->after('task_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_attachments');
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'custom_task_type')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('custom_task_type');
            });
        }
    }
};
