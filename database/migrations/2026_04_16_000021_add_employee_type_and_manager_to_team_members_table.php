<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('team_members')) {
            return;
        }

        $addEmployeeType = !Schema::hasColumn('team_members', 'employee_type');
        $addManagerId = !Schema::hasColumn('team_members', 'manager_id');

        if ($addEmployeeType || $addManagerId) {
            Schema::table('team_members', function (Blueprint $table) use ($addEmployeeType, $addManagerId) {
                if ($addEmployeeType) {
                    $table->string('employee_type')->default('technical');
                }

                if ($addManagerId) {
                    $table->foreignId('manager_id')
                        ->nullable()
                        ->constrained('team_members')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasColumn('team_members', 'employee_type')) {
            DB::table('team_members')
                ->whereNull('employee_type')
                ->update(['employee_type' => 'technical']);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('team_members')) {
            return;
        }

        if (Schema::hasColumn('team_members', 'manager_id')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->dropConstrainedForeignId('manager_id');
            });
        }

        if (Schema::hasColumn('team_members', 'employee_type')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->dropColumn('employee_type');
            });
        }
    }
};
