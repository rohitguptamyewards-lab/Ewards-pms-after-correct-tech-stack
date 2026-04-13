<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('project_transfers')) {
            Schema::create('project_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('from_user')->constrained('team_members');
                $table->foreignId('to_user')->constrained('team_members');
                $table->text('notes');
                $table->timestamp('created_at')->nullable();

                $table->index('project_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_transfers');
    }
};
