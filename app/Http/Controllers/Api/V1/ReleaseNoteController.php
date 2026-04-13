<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReleaseNoteController extends Controller
{
    private function authRole(): string
    {
        return auth()->user()?->role ?? '';
    }

    private function canCreate(): bool
    {
        return auth()->check();
    }

    private function canDelete(): bool
    {
        return in_array($this->authRole(), ['manager', 'analyst_head']);
    }

    /**
     * Web page: list all release notes across all projects.
     */
    public function allIndex()
    {
        if (!Schema::hasTable('release_notes') || !Schema::hasTable('release_note_files') || !Schema::hasTable('release_note_links')) {
            return Inertia::render('ReleaseNotes/Index', [
                'projects'         => [],
                'releaseNotes'     => [],
                'canCreate'        => false,
                'canDelete'        => false,
                'migrationPending' => true,
            ]);
        }

        try {
            $projects = DB::table('projects')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

            $allNotes = DB::table('release_notes')
                ->leftJoin('team_members', 'release_notes.created_by', '=', 'team_members.id')
                ->leftJoin('projects', 'release_notes.project_id', '=', 'projects.id')
                ->select('release_notes.*', 'team_members.name as author_name', 'team_members.role as author_role', 'projects.name as project_name')
                ->orderByDesc('release_notes.created_at')
                ->get();

            foreach ($allNotes as $note) {
                $note->files = DB::table('release_note_files')
                    ->where('release_note_id', $note->id)
                    ->orderByDesc('created_at')
                    ->get()->toArray();

                $note->links = DB::table('release_note_links')
                    ->where('release_note_id', $note->id)
                    ->orderByDesc('created_at')
                    ->get()->toArray();
            }

            return Inertia::render('ReleaseNotes/Index', [
                'projects'         => $projects,
                'releaseNotes'     => $allNotes->toArray(),
                'canCreate'        => $this->canCreate(),
                'canDelete'        => $this->canDelete(),
                'migrationPending' => false,
            ]);
        } catch (\Throwable $e) {
            \Log::error('ReleaseNotes allIndex error: ' . $e->getMessage());
            return Inertia::render('ReleaseNotes/Index', [
                'projects'         => [],
                'releaseNotes'     => [],
                'canCreate'        => false,
                'canDelete'        => false,
                'migrationPending' => true,
            ]);
        }
    }

    /**
     * Web page: list release notes for a project.
     */
    public function index(Request $request, int $projectId)
    {
        if (!Schema::hasTable('release_notes') || !Schema::hasTable('release_note_files') || !Schema::hasTable('release_note_links')) {
            if ($request->expectsJson()) return response()->json([]);
            return Inertia::render('ReleaseNotes/Index', [
                'project'          => null,
                'releaseNotes'     => [],
                'canCreate'        => false,
                'canDelete'        => false,
                'migrationPending' => true,
            ]);
        }

        $project = DB::table('projects')->where('id', $projectId)->first();
        abort_unless($project, 404);

        try {
            $notes = $this->getNotesForProject($projectId);
        } catch (\Throwable $e) {
            \Log::error('ReleaseNotes index error: ' . $e->getMessage());
            if ($request->expectsJson()) return response()->json([], 200);
            return Inertia::render('ReleaseNotes/Index', [
                'project'          => $project,
                'releaseNotes'     => [],
                'canCreate'        => false,
                'canDelete'        => false,
                'migrationPending' => true,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json($notes);
        }

        return Inertia::render('ReleaseNotes/Index', [
            'project'      => $project,
            'releaseNotes' => $notes,
            'canCreate'    => $this->canCreate(),
            'canDelete'    => $this->canDelete(),
        ]);
    }

    /**
     * One-time in-app table setup — creates the three release note tables
     * if they don't exist. Manager/analyst_head only.
     */
    public function setupTables(): JsonResponse
    {
        abort_unless($this->canDelete(), 403, 'Only managers can run setup.');

        try {
            if (!Schema::hasTable('release_notes')) {
                Schema::create('release_notes', function (\Illuminate\Database\Schema\Blueprint $table) {
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
                Schema::create('release_note_files', function (\Illuminate\Database\Schema\Blueprint $table) {
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
                Schema::create('release_note_links', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('release_note_id');
                    $table->string('label')->nullable();
                    $table->text('url');
                    $table->timestamps();
                    $table->foreign('release_note_id')->references('id')->on('release_notes')->cascadeOnDelete();
                });
            }

            return response()->json(['message' => 'Release notes tables are ready.']);
        } catch (\Throwable $e) {
            \Log::error('Release notes setup failed: ' . $e->getMessage());
            return response()->json(['message' => 'Setup failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a new release note with files and links.
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        abort_unless($this->canCreate(), 403, 'Only lead members and analysts can create release notes.');

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'files'       => ['nullable', 'array'],
            'files.*'     => ['file', 'max:20480'], // 20MB
            'links'       => ['nullable', 'array'],
            'links.*.label' => ['nullable', 'string', 'max:255'],
            'links.*.url'   => ['required', 'string', 'max:2048'],
        ]);

        $noteId = DB::table('release_notes')->insertGetId([
            'project_id'  => $projectId,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'created_by'  => auth()->id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Upload files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                try {
                    $path = $file->store("release-notes/{$projectId}", 'public');
                    DB::table('release_note_files')->insert([
                        'release_note_id' => $noteId,
                        'original_name'   => $file->getClientOriginalName(),
                        'stored_path'     => $path,
                        'mime_type'       => $file->getMimeType(),
                        'size'            => $file->getSize(),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning("Release note file upload failed: " . $e->getMessage());
                }
            }
        }

        // Save links
        if (!empty($data['links'])) {
            foreach ($data['links'] as $link) {
                DB::table('release_note_links')->insert([
                    'release_note_id' => $noteId,
                    'label'           => $link['label'] ?? null,
                    'url'             => $link['url'],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        return response()->json($this->getNoteById($noteId), 201);
    }

    /**
     * Update a release note.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($this->canCreate(), 403);

        $data = $request->validate([
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        DB::table('release_notes')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));

        return response()->json($this->getNoteById($id));
    }

    /**
     * Delete a release note (lead member permission required).
     */
    public function destroy(int $id): JsonResponse
    {
        abort_unless($this->canDelete(), 403, 'Only lead members (manager/analyst_head) can delete release notes.');

        $note = DB::table('release_notes')->where('id', $id)->first();
        abort_unless($note, 404);

        // Delete files from storage
        $files = DB::table('release_note_files')->where('release_note_id', $id)->get();
        foreach ($files as $file) {
            Storage::disk('public')->delete($file->stored_path);
        }

        DB::table('release_notes')->where('id', $id)->delete(); // cascade handles children

        return response()->json(['message' => 'Release note deleted']);
    }

    /**
     * Upload additional files to an existing release note.
     */
    public function uploadFiles(Request $request, int $id): JsonResponse
    {
        abort_unless($this->canCreate(), 403);

        $request->validate([
            'files'   => ['required', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);

        $note = DB::table('release_notes')->where('id', $id)->first();
        abort_unless($note, 404);

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            $path = $file->store("release-notes/{$note->project_id}", 'public');
            $fileId = DB::table('release_note_files')->insertGetId([
                'release_note_id' => $id,
                'original_name'   => $file->getClientOriginalName(),
                'stored_path'     => $path,
                'mime_type'       => $file->getMimeType(),
                'size'            => $file->getSize(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $uploaded[] = DB::table('release_note_files')->where('id', $fileId)->first();
        }

        return response()->json($uploaded);
    }

    /**
     * Delete a file from a release note.
     */
    public function deleteFile(int $fileId): JsonResponse
    {
        abort_unless($this->canDelete(), 403);

        $file = DB::table('release_note_files')->where('id', $fileId)->first();
        abort_unless($file, 404);

        Storage::disk('public')->delete($file->stored_path);
        DB::table('release_note_files')->where('id', $fileId)->delete();

        return response()->json(['message' => 'File deleted']);
    }

    /**
     * Add a link to a release note.
     */
    public function addLink(Request $request, int $id): JsonResponse
    {
        abort_unless($this->canCreate(), 403);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'url'   => ['required', 'string', 'max:2048'],
        ]);

        $linkId = DB::table('release_note_links')->insertGetId([
            'release_note_id' => $id,
            'label'           => $data['label'] ?? null,
            'url'             => $data['url'],
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(DB::table('release_note_links')->where('id', $linkId)->first());
    }

    /**
     * Delete a link.
     */
    public function deleteLink(int $linkId): JsonResponse
    {
        abort_unless($this->canDelete(), 403);

        DB::table('release_note_links')->where('id', $linkId)->delete();

        return response()->json(['message' => 'Link deleted']);
    }

    // ── Helpers ──────────────────────────────────────────

    private function getNotesForProject(int $projectId): array
    {
        $notes = DB::table('release_notes')
            ->leftJoin('team_members', 'release_notes.created_by', '=', 'team_members.id')
            ->select('release_notes.*', 'team_members.name as author_name', 'team_members.role as author_role')
            ->where('release_notes.project_id', $projectId)
            ->orderByDesc('release_notes.created_at')
            ->get();

        foreach ($notes as $note) {
            $note->files = DB::table('release_note_files')
                ->where('release_note_id', $note->id)
                ->orderByDesc('created_at')
                ->get()
                ->toArray();

            $note->links = DB::table('release_note_links')
                ->where('release_note_id', $note->id)
                ->orderByDesc('created_at')
                ->get()
                ->toArray();
        }

        return $notes->toArray();
    }

    private function getNoteById(int $id): ?object
    {
        $note = DB::table('release_notes')
            ->leftJoin('team_members', 'release_notes.created_by', '=', 'team_members.id')
            ->select('release_notes.*', 'team_members.name as author_name', 'team_members.role as author_role')
            ->where('release_notes.id', $id)
            ->first();

        if (!$note) return null;

        $note->files = DB::table('release_note_files')
            ->where('release_note_id', $id)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();

        $note->links = DB::table('release_note_links')
            ->where('release_note_id', $id)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();

        return $note;
    }
}
