<?php
namespace App\Enums;

enum WorkerRole: string
{
    case Owner = 'owner';
    case Contributor = 'contributor';

    public function label(): string
    {
        return match($this) {
            self::Owner => 'Owner',
            self::Contributor => 'Contributor',
        };
    }
}
