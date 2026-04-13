<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('project_stages')) {
            Schema::create('project_stages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('stage_name')->index();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('project_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_stages');
    }
};
