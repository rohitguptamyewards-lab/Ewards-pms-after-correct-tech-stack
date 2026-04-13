<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action');
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id');
                $table->json('changes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['entity_type', 'entity_id']);
                $table->index('user_id');
                $table->index('action');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
