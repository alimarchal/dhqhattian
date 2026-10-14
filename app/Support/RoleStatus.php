<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Roles can be switched off (roles.is_active). The column comes from a migration, so every
 * helper here also works before that migration has run (all roles count as active then).
 */
class RoleStatus
{
    private static ?bool $hasColumn = null;

    public static function supported(): bool
    {
        return self::$hasColumn ??= Schema::hasColumn(config('permission.table_names.roles', 'roles'), 'is_active');
    }

    /** Only roles that can be given to users; $keepIds are always included (roles a user already has). */
    public static function assignable(Builder $query, array $keepIds = []): Builder
    {
        if (! self::supported()) {
            return $query;
        }

        return $query->where(function ($q) use ($keepIds) {
            $q->where('is_active', true);
            if ($keepIds) {
                $q->orWhereIn('id', $keepIds);
            }
        });
    }
}
