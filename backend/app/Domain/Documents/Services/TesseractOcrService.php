<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\DocumentVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class TesseractOcrService
{
    /**
     * @return array{text:string,pages:array<int,string>,method:string}
     */
    public function extract(DocumentVersion $version): array
    {
        $absolutePath = Storage::disk($version->storage_disk)->path($version->storage_path);

        if (in_array($version->mime_type, ['image/jpeg', 'image/png'], true)) {
            $text = $this->ocrImage($absolutePath);
            return ['text' => $text, 'pages' => [1 => $text], 'method' => 'ocr_tesseract'];
        }

        if ($version->mime_type === 'application/pdf') {
            return $this->ocrPdf($absolutePath);
        }

        return ['text' => '', 'pages' => [], 'method' => 'ocr_unavailable'];
    }

    private function ocrImage(string $path): string
    {
        $binary = (string) config('documents.intelligence.tesseract_binary', 'tesseract');

        try {
            $process = new Process([
                $binary,
                $path,
                'stdout',
                '-l',
                (string) config('documents.intelligence.ocr_language', 'eng'),
                '--psm',
                '6',
            ]);
            $process->setTimeout((int) config('documents.intelligence.process_timeout', 120));
            $process->run();

            return $process->isSuccessful()
                ? trim($process->getOutput())
                : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array{text:string,pages:array<int,string>,method:string}
     */
    private function ocrPdf(string $path): array
    {
        $pdftoppm = (string) config('documents.intelligence.pdftoppm_binary', 'pdftoppm');
        $tmpDir = storage_path('app/tmp/ocr/'.Str::uuid());

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0700, true);
        }

        $prefix = $tmpDir.DIRECTORY_SEPARATOR.'page';

        try {
            $render = new Process([$pdftoppm, '-png', '-r', '180', $path, $prefix]);
            $render->setTimeout((int) config('documents.intelligence.process_timeout', 120));
            $render->run();

            if (!$render->isSuccessful()) {
                return ['text' => '', 'pages' => [], 'method' => 'ocr_pdf_failed'];
            }

            $files = glob($prefix.'-*.png') ?: [];
            natsort($files);

            $pages = [];
            $pageNumber = 1;

            foreach ($files as $image) {
                $pages[$pageNumber++] = $this->ocrImage($image);
            }

            return [
                'text' => trim(implode("\n\n", $pages)),
                'pages' => $pages,
                'method' => 'ocr_tesseract_pdf',
            ];
        } catch (\Throwable) {
            return ['text' => '', 'pages' => [], 'method' => 'ocr_pdf_failed'];
        } finally {
            foreach (glob($tmpDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($tmpDir);
        }
    }
}
