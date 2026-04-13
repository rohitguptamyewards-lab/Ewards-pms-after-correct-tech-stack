<?php
namespace App\Http\Requests;

use App\Enums\PlannerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlannerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'project_id'     => ['required', 'integer', 'exists:projects,id'],
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'milestone_flag' => ['sometimes', 'boolean'],
            'assigned_to'    => ['nullable', 'integer', 'exists:team_members,id'],
            'due_date'       => ['nullable', 'date'],
            'status'         => ['sometimes', Rule::in(array_column(PlannerStatus::cases(), 'value'))],
        ];
    }
}
