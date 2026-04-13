<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('project_planners')) {
            Schema::create('project_planners', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->boolean('milestone_flag')->default(false);
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status')->default('pending');
                $table->integer('order_index')->default(0);
                $table->timestamps();

                $table->index('project_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_planners');
    }
};
