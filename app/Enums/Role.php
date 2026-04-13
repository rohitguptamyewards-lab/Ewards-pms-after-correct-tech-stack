<?php
namespace App\Enums;

enum Role: string
{
    case Manager = 'manager';
    case AnalystHead = 'analyst_head';
    case Analyst = 'analyst';
    case SeniorDeveloper = 'senior_developer';
    case Developer = 'developer';
    case Employee = 'employee'; // kept for backward compat

    /**
     * Admin-level roles that have full access
     */
    public static function adminRoles(): array
    {
        return ['manager', 'analyst_head'];
    }

    /**
     * Roles that can create projects and manage assignments
     */
    public static function managerRoles(): array
    {
        return ['manager', 'analyst_head', 'analyst'];
    }

    public static function canCreateProject(): array
    {
        return ['manager', 'analyst_head', 'analyst'];
    }

    /**
     * Check if a role string is an admin
     */
    public static function isAdmin(string $role): bool
    {
        return in_array($role, self::adminRoles());
    }

    public function label(): string
    {
        return match($this) {
            self::Manager => 'Manager',
            self::AnalystHead => 'Analyst Head',
            self::Analyst => 'Analyst',
            self::SeniorDeveloper => 'Senior Developer',
            self::Developer => 'Developer',
            self::Employee => 'Employee',
        };
    }
}
