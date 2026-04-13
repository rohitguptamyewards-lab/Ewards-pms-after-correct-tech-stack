<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add joined_date to team_members
        if (Schema::hasTable('team_members') && !Schema::hasColumn('team_members', 'joined_date')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->date('joined_date')->nullable()->after('role');
            });
        }

        // Update existing 'employee' roles to 'developer' for consistency
        // (existing employees become developers by default)
        // No - keep backward compat. The new roles are additive.

        // Make work_logs.status nullable (timer entries won't have status)
        // SQLite doesn't support ALTER COLUMN, so we leave it as-is.
        // The default 'done' is fine.
    }

    public function down(): void
    {
        if (Schema::hasTable('team_members') && Schema::hasColumn('team_members', 'joined_date')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->dropColumn('joined_date');
            });
        }
    }
};
