<?php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ProjectController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth');

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Projects
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/custom-worklog', [ProjectController::class, 'customWorklogIndex'])->name('projects.custom-worklog');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/board', [ProjectController::class, 'board'])->name('projects.board');
    Route::get('/projects/{id}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('/projects/{id}', [ProjectController::class, 'update'])->name('projects.update');
    Route::get('/projects/{id}/replicate', [ProjectController::class, 'replicate'])->name('projects.replicate');

    // Work Logs
    Route::get('/work-logs', [\App\Http\Controllers\Api\V1\WorkLogController::class, 'index'])->name('work-logs.index');
    Route::get('/work-logs/create', [\App\Http\Controllers\Api\V1\WorkLogController::class, 'create'])->name('work-logs.create');
    Route::post('/work-logs', [\App\Http\Controllers\Api\V1\WorkLogController::class, 'store'])->name('work-logs.store');

    // Reports
    Route::get('/reports/dashboard', [\App\Http\Controllers\Api\V1\ReportController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('/reports/projects', [\App\Http\Controllers\Api\V1\ReportController::class, 'projects'])->name('reports.projects');
    Route::get('/reports/workers', [\App\Http\Controllers\Api\V1\ReportController::class, 'workers'])->name('reports.workers');

    // Automations
    Route::get('/automations', [\App\Http\Controllers\Api\V1\AutomationController::class, 'index'])->name('automations.index');

    // Release Notes
    Route::get('/release-notes', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'allIndex'])->name('release-notes.all');
    Route::get('/projects/{projectId}/release-notes', [\App\Http\Controllers\Api\V1\ReleaseNoteController::class, 'index'])->name('release-notes.index');

    // Team Members
    Route::get('/team-members', [\App\Http\Controllers\Api\V1\TeamMemberController::class, 'index'])->name('team-members.index');
    Route::get('/team-members/create', [\App\Http\Controllers\Api\V1\TeamMemberController::class, 'create'])->name('team-members.create');
    Route::post('/team-members', [\App\Http\Controllers\Api\V1\TeamMemberController::class, 'store'])->name('team-members.store');
});
