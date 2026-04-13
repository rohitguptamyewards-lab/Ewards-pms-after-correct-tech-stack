<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('project_updates')) {
            Schema::create('project_updates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->text('content');
                $table->string('source')->default('manual');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('project_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
    }
};
