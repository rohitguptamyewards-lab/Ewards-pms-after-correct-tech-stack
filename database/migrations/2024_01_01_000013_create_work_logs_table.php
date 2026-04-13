<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('work_logs')) {
            Schema::create('work_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('team_members')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->date('log_date');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->decimal('hours_spent', 8, 2)->default(0);
                $table->string('status')->default('done');
                $table->text('note')->nullable();
                $table->text('blocker')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'log_date']);
                $table->index('project_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
