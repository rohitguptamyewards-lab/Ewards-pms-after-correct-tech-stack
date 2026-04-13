<?php
namespace App\Enums;

enum WorkType: string
{
    case FrontendWork = 'frontend_work';
    case BackendWork = 'backend_work';
    case Figma = 'figma';
    case TriggerPart = 'trigger_part';
    case DataMapping = 'data_mapping';
    case FullStack = 'full_stack';
    case Other = 'other';

    public function label(): string
    {
        return match($this) {
            self::FrontendWork => 'Frontend Work',
            self::BackendWork => 'Backend Work',
            self::Figma => 'Figma',
            self::TriggerPart => 'Trigger Part',
            self::DataMapping => 'Data Mapping',
            self::FullStack => 'Full Stack',
            self::Other => 'Other',
        };
    }
}
