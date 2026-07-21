<?php

namespace App\Enums;

/**
 * Organization membership roles (team tier, task 5.2). Kept minimal per
 * SPEC: the owner manages the organization (invites, removals); admin and
 * member currently carry identical read-only membership and exist so the
 * role vocabulary can grow without a migration.
 */
enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
}
