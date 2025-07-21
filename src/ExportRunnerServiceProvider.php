<?php

namespace Bangsamu\ExportRunner;

use Illuminate\Support\ServiceProvider;

class ExportRunnerServiceProvider extends ServiceProvider
{
    public function boot()
    {
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
