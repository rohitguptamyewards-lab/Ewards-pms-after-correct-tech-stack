<?php
namespace App\Http\Requests;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\TaskType;
use App\Enums\WorkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'objective'          => ['nullable', 'string'],
            'tags'               => ['nullable', 'array'],
            'owner_id'           => ['required', 'integer', 'exists:team_members,id'],
            'status'             => ['sometimes', Rule::in(array_column(ProjectStatus::cases(), 'value'))],
            'priority'           => ['sometimes', Rule::in(array_column(ProjectPriority::cases(), 'value'))],
            'work_type'          => ['nullable', Rule::in(array_column(WorkType::cases(), 'value'))],
            'task_type'          => ['nullable', Rule::in(array_column(TaskType::cases(), 'value'))],
            'custom_task_type'   => ['nullable', 'string', 'max:255'],
            'ticket_link'        => ['nullable', 'string', 'max:2000'],
            'analyst_id'         => ['nullable', 'integer', 'exists:team_members,id'],
            'analyst_testing_id' => ['nullable', 'integer', 'exists:team_members,id'],
            'developer_id'       => ['nullable', 'integer', 'exists:team_members,id'],
            'document_link'      => ['nullable', 'string', 'max:2000'],
            'ai_chat_link'       => ['nullable', 'string', 'max:2000'],
            'parent_id'          => ['nullable', 'integer', 'exists:projects,id'],
            'start_date'         => ['nullable', 'date'],
            'due_date'           => ['nullable', 'date', 'after_or_equal:start_date'],
            'linked_project_ids' => ['nullable', 'array'],
        ];
    }
}
