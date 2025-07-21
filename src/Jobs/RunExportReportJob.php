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
            $fullPath = storage_path($log->file_name);
            if (!file_exists($fullPath)) {
                Log::warning("File tidak ditemukan: $fullPath");
                return;
            }

            // Buat file ZIP
            $zipName = 'report_' . Str::random(8) . '.zip';
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

            // Cek ukuran file
            $fileSize = filesize($zipPath);
            $maxSize = 8 * 1024 * 1024; // 8 MB

            if ($fileSize > $maxSize) {
                // Jika file terlalu besar, kirim link download
                $publicUrl = asset("storage/tmp_zipped/{$zipName}");

                // Mail::raw("Report is ready. download:\n\n$publicUrl", function ($message) {
                //     $message->to($this->email)
                //             ->subject('Your Report is Ready');
                // });
                Mail::html("Report is ready. <br><br><a href=\"$publicUrl\">download</a>", function ($message) {
                    $message->to($this->email)
                            ->subject('Your Report is Ready');
                });

                Log::info("📩 Email dikirim dengan link karena ZIP > 8MB", ['link' => $publicUrl]);
            } else {
                // Jika ukuran cukup, attach ZIP
                Mail::raw('Report is ready. File attach.', function ($message) use ($zipPath) {
                    $message->to($this->email)
                            ->subject('Your Report')
                            ->attach($zipPath);
                });

                Log::info("📩 Email dikirim dengan attachment ZIP", ['file' => $zipPath]);
            }


            /*
            // $date = now()->format('ymd');
            // $filename = "report_{$this->reportId}.xlsx";
            // $filePath = base_path("go_reports/output/{$date}/{$filename}");

            // if (file_exists($filePath)) {
            //     Mail::raw('Report is ready.', function ($message) use ($filePath) {
            //         $message->to($this->email)
            //                 ->subject('Your Report')
            //                 ->attach($filePath);
            //     });
            // }

            $log = DB::table('report_log')
                ->where('job_id', $jobId)
                ->where('status', 'success')
                ->orderByDesc('created_at')
                ->first();


            $path_file = storage_path($log->file_name);

            if ($log && file_exists($path_file)) {
                Mail::raw('Report is ready.', function ($message) use ($log,$path_file) {
                    $message->to($this->email)
                            ->subject('Your Report')
                            ->attach($path_file);
                });
            } else {
                Log::warning("Report file:: ".$path_file." (".file_exists($path_file).") not found for job_id: $jobId");
            }


            */

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
