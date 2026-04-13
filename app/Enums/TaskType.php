<?php
namespace App\Enums;

enum TaskType: string
{
    case NewProject = 'new_project';
    case AdditionOnExisting = 'addition_on_existing';
    case BugFix = 'bug_fix';
    case DataMapping = 'data_mapping';
    case Integration = 'integration';
    case Other = 'other';

    public function label(): string
    {
        return match($this) {
            self::NewProject => 'New Project',
            self::AdditionOnExisting => 'Addition on Existing',
            self::BugFix => 'Bug Fix',
            self::DataMapping => 'Data Mapping',
            self::Integration => 'Integration',
            self::Other => 'Other',
        };
    }
}
