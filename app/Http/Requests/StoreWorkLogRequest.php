<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkLogRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'project_id'   => ['nullable', 'integer', 'exists:projects,id', 'required_without:project_name'],
            'project_name' => ['nullable', 'string', 'max:255', 'required_without:project_id', 'regex:/\\S/'],
            'log_date'     => ['required', 'date'],
            'start_time'   => ['required', 'date_format:H:i'],
            'end_time'     => ['required', 'date_format:H:i', 'after:start_time'],
            'status'       => ['nullable', 'in:done,in_progress,blocked'],
            'note'         => ['required', 'string', 'min:1'],
            'blocker'      => ['nullable', 'string'],
        ];
    }
}
