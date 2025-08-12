<?php

namespace Bangsamu\ExportRunner;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class ExportRunnerServiceProvider extends ServiceProvider
{
    public function boot()
    {
    Log::info('ExportRunnerServiceProvider boot called');
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        // Publish binary ke base_path atau jalankan langsung dari package
        $this->publishes([
            __DIR__.'/../bin/report_export_excel' => base_path('report_export_excel'),
        ], 'export-runner-bin');
    }

    public function register()
    {
        //
    }
}
