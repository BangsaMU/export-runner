<?php

namespace Bangsamu\ExportRunner\Helpers;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Runner
{
    /**
     * Jalankan binary export report dengan param.
     *
     * @param int $reportId
     * @param array $params
     * @return string Output dari proses
     * @throws \Exception
     */
    public static function run(int $reportId, array $params): string
    {
        // $binary = base_path('report_export_excel'); //jika mau publish
        $binary = __DIR__ . '/../../bin/report_export_excel';  // tetap dalam vendor folder


        $args = [$binary, (string) $reportId];
        foreach ($params as $k => $v) {
            $args[] = "{$k}={$v}";
        }

        $process = new Process($args);
        $process->setTimeout(300); // optional: 5 menit

        try {
            $process->mustRun();
            return $process->getOutput();
        } catch (ProcessFailedException $e) {
            throw new \Exception("Export failed: " . $e->getMessage());
        }
    }
}
