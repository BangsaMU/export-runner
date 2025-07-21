<?php

namespace Bangsamu\ExportRunner\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Contracts\Queue\ShouldQueue;


use Illuminate\Foundation\Bus\Dispatchable; // <== ini penting

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunExportReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reportId;
    protected $params;
    protected $email;

    public function __construct(int $reportId, array $params, string $email)
    {
        $this->reportId = $reportId;
        $this->params = $params;
        $this->email = $email;
    }

    public function handle(): void
    {
        // $binary = base_path('report_export_excel'); //jika mau publish
        $binary = __DIR__ . '/../../bin/report_export_excel';  // tetap dalam vendor folder
        if (!file_exists($binary)) {
            Log::error("Binary not found: {$binary}");
            return;
        }else{
            Log::info("Binary found: {$binary}");
        }
        Log::info('RunExportReportJob::handle masuk', [
            'reportId' => $this->reportId,
            'params' => $this->params,
            'email' => $this->email
        ]);

        $args = [$binary, (string) $this->reportId];
        foreach ($this->params as $k => $v) {
            $args[] = "{$k}={$v}";
        }

        // Inject ENV ke proses Go
        $env = [
            'DB_HOST' => env('DB_HOST'),
            'DB_PORT' => env('DB_PORT'),
            'DB_USER' => env('DB_USERNAME'),
            'DB_PASS' => env('DB_PASSWORD'),
            'DB_NAME' => env('DB_DATABASE'),
        ];

        $process = new Process($args, base_path(), $env);

        // $process = new Process($args);
        try {
            $process->mustRun();
            Log::info("Export success", ['output' => $process->getOutput()]);

            $date = now()->format('ymd');
            $filename = "report_{$this->reportId}.xlsx";
            $filePath = base_path("go_reports/output/{$date}/{$filename}");

            if (file_exists($filePath)) {
                Mail::raw('Report is ready.', function ($message) use ($filePath) {
                    $message->to($this->email)
                            ->subject('Your Report')
                            ->attach($filePath);
                });
            }
        } catch (ProcessFailedException $e) {
            Log::error("Export failed", ['error' => $e->getMessage()]);
        }
    }
}
