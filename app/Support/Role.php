<?php

namespace App\Support;

/**
 * User roles and the role-based permission matrix (FR08).
 */
final class Role
{
    public const ADMIN = 'admin';
    public const DIRECTOR = 'director';
    public const COORDINATOR = 'coordinator';
    public const FINANCE = 'finance';
    public const VOLUNTEER = 'volunteer';
    public const DONOR = 'donor';

    public const LABELS = [
        self::ADMIN => 'Administrator',
        self::DIRECTOR => 'Director',
        self::COORDINATOR => 'Project Coordinator',
        self::FINANCE => 'Finance Staff',
        self::VOLUNTEER => 'Volunteer',
        self::DONOR => 'Donor / Partner',
    ];

    /** Ability => roles allowed to perform it. */
    public const ABILITIES = [
        'beneficiaries.view' => [self::ADMIN, self::DIRECTOR, self::COORDINATOR],
        'beneficiaries.manage' => [self::ADMIN, self::COORDINATOR],
        'donors.view' => [self::ADMIN, self::DIRECTOR, self::FINANCE],
        'donors.manage' => [self::ADMIN, self::FINANCE],
        'donations.view' => [self::ADMIN, self::DIRECTOR, self::FINANCE],
        'donations.manage' => [self::ADMIN, self::FINANCE],
        'distributions.view' => [self::ADMIN, self::DIRECTOR, self::COORDINATOR, self::FINANCE],
        'distributions.manage' => [self::ADMIN, self::COORDINATOR],
        'programs.view' => [self::ADMIN, self::DIRECTOR, self::COORDINATOR, self::FINANCE],
        'programs.manage' => [self::ADMIN, self::DIRECTOR, self::COORDINATOR],
        'volunteers.view' => [self::ADMIN, self::DIRECTOR, self::COORDINATOR],
        'volunteers.manage' => [self::ADMIN, self::COORDINATOR],
        'schedule.own' => [self::VOLUNTEER],
        'messages.use' => [self::ADMIN, self::DIRECTOR, self::COORDINATOR, self::VOLUNTEER],
        'reports.view' => [self::ADMIN, self::DIRECTOR, self::FINANCE],
        'impact.view' => [self::DONOR],
        'users.manage' => [self::ADMIN, self::DIRECTOR],
        'audit.view' => [self::ADMIN, self::DIRECTOR],
        'settings.manage' => [self::ADMIN],
    ];

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $role): string
    {
        return self::LABELS[$role] ?? ucfirst($role);
    }

    public static function allows(string $role, string $ability): bool
    {
        return in_array($role, self::ABILITIES[$ability] ?? [], true);
    }
}
