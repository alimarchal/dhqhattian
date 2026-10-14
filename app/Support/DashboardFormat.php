<?php

namespace App\Support;

class DashboardFormat
{
    /** Percentage change as [tone, text] for the little up / down badges. */
    public static function change(float $now, float $before, string $suffix = 'vs yesterday'): array
    {
        if ($before == 0) {
            return $now == 0 ? ['flat', 'no change'] : ['up', 'new'];
        }
        $pct = (int) round(($now - $before) / $before * 100);

        return [$pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'), ($pct > 0 ? '▲ ' : ($pct < 0 ? '▼ ' : '')).abs($pct).'% '.$suffix];
    }
}
