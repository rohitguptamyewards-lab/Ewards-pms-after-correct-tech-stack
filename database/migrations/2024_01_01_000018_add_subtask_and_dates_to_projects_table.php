<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable();
            }
            if (!Schema::hasColumn('projects', 'start_date')) {
                $table->date('start_date')->nullable();
            }
            if (!Schema::hasColumn('projects', 'due_date')) {
                $table->date('due_date')->nullable();
            }
        });

        // Add foreign key and index separately to avoid duplicate errors
        try {
            Schema::table('projects', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('projects')->onDelete('cascade');
                $table->index('parent_id');
            });
        } catch (\Throwable $e) {
            // Index/FK may already exist
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn(['parent_id', 'start_date', 'due_date']);
        });
    }
};
