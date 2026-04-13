<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->text('objective')->nullable();
                $table->json('tags')->nullable();
                $table->string('status')->default('active')->index();
                $table->string('priority')->default('medium');
                $table->foreignId('owner_id')->constrained('team_members')->restrictOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->json('linked_project_ids')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('owner_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
