<?php

namespace Bangsamu\ExportRunner\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Contracts\Queue\ShouldQueue;


use Illuminate\Foundation\Bus\Dispatchable; // <== ini penting

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;


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
        $this->params['job_id'] = Str::uuid()->toString(); // 🆕 Generate UUID
        $this->params['user'] = $this->params['user']??$email; // jika tidak ada user ambil dar iemail
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
        $db = config('database.connections.mysql');

        $env = [
            'DB_HOST' => $db['host'],
            'DB_PORT' => $db['port'],
            'DB_USER' => $db['username'],
            'DB_PASS' => $db['password'],
            'DB_NAME' => $db['database'],
        ];

        if(config('app.debug')==true){
            Log::info("env: ".json_encode($env));
        }

        $process = new Process($args, base_path(), $env);

        // $process = new Process($args);
        try {
            $process->mustRun();

            $jobId = $this->params['job_id'];

            Log::info("Export success jobId::".$jobId , ['output' => $process->getOutput()]);


            $log = DB::table('report_log')
                ->where('job_id', $jobId)
                ->where('status', 'success')
                ->orderByDesc('created_at')
                ->first();

            if (!$log) {
                Log::warning("Tidak ada log untuk job_id $jobId");
                return;
            }

            // Path asli file hasil Go
            $relativePath = ltrim(preg_replace('#^storage/?#', '', $log->file_name), '/');
            $fullPath = storage_path($relativePath);
            // if (!file_exists($fullPath)) {
            //     Log::warning("File tidak ditemukan: $fullPath");
            //     return;
            // }

            $realPath = realpath($fullPath);

            if (!$realPath || !file_exists($fullPath)) {
                Log::warning("File tidak ditemukan (realpath): $fullPath");
                return;
            }

            // Buat file ZIP
            // $zipName = 'report_' . Str::random(8) . '.zip';
            $zipName = 'report_' . Str::slug($log->report_name) . '_' . date('Ymd_His') . '.zip';

            $zipPath = storage_path("app/public/tmp_zipped/{$zipName}");
            $oldUmask = umask(0002); // 👈 izin default: 775

            $path = storage_path('app/public/tmp_zipped');
            if (!file_exists($path)) {
                mkdir($path, 0775, true); // 0775 = rwxrwxr-x
                chmod($path, 0775);       // Pastikan izin folder sesuai
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFile($fullPath, basename($log->file_name)); // nama di dalam zip
                $zip->close();

                // set permission langsung
                chmod($zipPath, 0775);
            } else {
                Log::error("Gagal membuat ZIP untuk file $fullPath");
                return;
            }

            umask($oldUmask); // kembalikan ke umask awal


            SendReportEmailJob::dispatch($this->email, $zipPath);



        } catch (ProcessFailedException $e) {
            Log::error("Export failed", ['error' => $e->getMessage()]);
        }
    }

    public function getParams()
    {
        return $this->params;
    }
    public function getJobId()
    {
        return $this->params['job_id'] ?? null;
    }
}
