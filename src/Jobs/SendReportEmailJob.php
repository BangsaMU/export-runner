<?php

namespace Bangsamu\ExportRunner\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;

class SendReportEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $email;
    protected string $reportPath;
    protected ?string $zipName;

    /**
     * Buat job.
     */
    public function __construct(string $email, string $reportPath, ?string $zipName = null)
    {
        $this->email = $email;
        $this->reportPath = $reportPath;
        $this->zipName = $zipName;
    }

    /**
     * Proses job.
     */
    public function handle(): void
    {
        if (!file_exists($this->reportPath)) {
            Log::warning("📁 File report tidak ditemukan: {$this->reportPath}");
            return;
        }

        $fileSize = filesize($this->reportPath);
        $maxSize = 8 * 1024 * 1024; // 8MB

        if ($fileSize > $maxSize) {
            // Kirim link download
            $publicPath = 'storage/tmp_zipped/' . basename($this->reportPath);
            $url = asset($publicPath);

            Mail::html("Report Anda telah selesai. Silakan unduh:<br><br><a href=\"$url\">Klik di sini</a>", function ($message) {
                $message->to($this->email)
                        ->subject('Report Anda Siap');
            });

            Log::info("📩 Email link dikirim ke {$this->email}", ['url' => $url]);
        } else {
            // Kirim sebagai lampiran
            Mail::raw("Report Anda siap. Silakan lihat lampiran.", function ($message) {
                $message->to($this->email)
                        ->subject('Report Anda Siap')
                        ->attach($this->reportPath);
            });

            Log::info("📩 Email attachment dikirim ke {$this->email}", ['file' => $this->reportPath]);
        }
    }

    /**
     * Retry 3x jika gagal
     */
    public $tries = 3;

    /**
     * Delay antara retry dalam detik
     */
    public $backoff = 10;
}
