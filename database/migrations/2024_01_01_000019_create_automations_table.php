<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automations')) {
            if (!Schema::hasTable('automation_logs')) {
                Schema::create('automation_logs', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('automation_id');
                    $table->string('status');
                    $table->text('message')->nullable();
                    $table->json('details')->nullable();
                    $table->timestamp('created_at');
                    $table->foreign('automation_id')->references('id')->on('automations')->onDelete('cascade');
                    $table->index('automation_id');
                });
            }
            return;
        }

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // Trigger
            $table->string('trigger_type');      // schedule, stage_change, status_change, blocker_created, due_date_approaching
            $table->json('trigger_config');       // frequency, day, time, from/to stage/status, etc.

            // Action
            $table->string('action_type');        // send_email, send_notification
            $table->json('action_config');        // recipients, subject, template, etc.

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index('trigger_type');
            $table->index('is_active');
        });

        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('automation_id');
            $table->string('status');             // success, failed
            $table->text('message')->nullable();
            $table->json('details')->nullable();  // projects matched, emails sent, etc.
            $table->timestamp('created_at');

            $table->foreign('automation_id')->references('id')->on('automations')->onDelete('cascade');
            $table->index('automation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automations');
    }
};
