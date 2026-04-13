<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('release_notes')) {
            Schema::create('release_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('created_by')->references('id')->on('team_members');
            });
        }

        if (!Schema::hasTable('release_note_files')) {
            Schema::create('release_note_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('release_note_id');
                $table->string('original_name');
                $table->string('stored_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->timestamps();

                $table->foreign('release_note_id')->references('id')->on('release_notes')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('release_note_links')) {
            Schema::create('release_note_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('release_note_id');
                $table->string('label')->nullable();
                $table->text('url');
                $table->timestamps();

                $table->foreign('release_note_id')->references('id')->on('release_notes')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('release_note_links');
        Schema::dropIfExists('release_note_files');
        Schema::dropIfExists('release_notes');
    }
};
