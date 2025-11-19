<?php

namespace App\Providers;

use App\Support\Database\SqliteDriveSynchronizer;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
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
    public function boot(SqliteDriveSynchronizer $synchronizer): void
    {
        if (! config('services.google_drive.sync_on_boot')) {
            return;
        }

        if (blank(config('services.google_drive.master_file_id'))) {
            return;
        }

        if (App::runningUnitTests()) {
            return;
        }

        try {
            $synchronizer->sync();
        } catch (\Throwable $e) {
            Log::warning('Google Drive sync failed: '.$e->getMessage());
        }
    }
}
