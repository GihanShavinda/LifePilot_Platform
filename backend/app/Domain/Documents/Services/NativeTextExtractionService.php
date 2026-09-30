<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\DocumentVersion;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class NativeTextExtractionService
{
    /**
     * @return array{text:string,pages:array<int,string>,method:string}
     */
    public function extract(DocumentVersion $version): array
    {
        $disk = Storage::disk($version->storage_disk);
        $absolutePath = $disk->path($version->storage_path);
        $mime = $version->mime_type;

        if ($mime === 'text/plain') {
            $text = (string) file_get_contents($absolutePath);
            return ['text' => $text, 'pages' => [1 => $text], 'method' => 'native_txt'];
        }

        if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $text = $this->extractDocx($absolutePath);
            return ['text' => $text, 'pages' => [1 => $text], 'method' => 'native_docx'];
        }

        if ($mime === 'application/pdf') {
            $text = $this->extractPdf($absolutePath);
            return ['text' => $text, 'pages' => [1 => $text], 'method' => 'native_pdf'];
        }

        return ['text' => '', 'pages' => [], 'method' => 'native_unavailable'];
    }

    private function extractDocx(string $path): string
    {
        if (!class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) {
            return '';
        }

        $xml = str_replace(
            ['</w:p>', '</w:tr>', '<w:tab/>'],
            ["\n", "\n", "\t"],
            $xml
        );

        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function extractPdf(string $path): string
    {
        $binary = (string) config('documents.intelligence.pdftotext_binary', 'pdftotext');

        try {
            $process = new Process([$binary, '-layout', '-nopgbrk', $path, '-']);
            $process->setTimeout((int) config('documents.intelligence.process_timeout', 60));
            $process->run();

            return $process->isSuccessful()
                ? trim($process->getOutput())
                : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
