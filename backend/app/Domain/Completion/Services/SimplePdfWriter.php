<?php

namespace App\Domain\Completion\Services;

class SimplePdfWriter
{
    public function render(string $title, array $lines): string
    {
        $pages = array_chunk(array_values($lines), 42);
        if ($pages === []) {
            $pages = [[]];
        }

        $objects = [];
        $catalogId = 1;
        $pagesId = 2;
        $fontId = 3;
        $nextId = 4;
        $pageIds = [];

        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        foreach ($pages as $index => $pageLines) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageIds[] = $pageId;

            $textLines = array_merge([$title, ''], $pageLines);
            $stream = "BT\n/F1 10 Tf\n50 790 Td\n";
            foreach ($textLines as $lineIndex => $line) {
                if ($lineIndex > 0) {
                    $stream .= "0 -16 Td\n";
                }
                $stream .= '('.$this->escape((string) $line).") Tj\n";
            }
            $stream .= "ET";

            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent '.$pagesId.' 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 '.$fontId.' 0 R >> >> /Contents '.$contentId.' 0 R >>';
        }

        $kids = implode(' ', array_map(fn (int $id) => $id.' 0 R', $pageIds));
        $objects[$pagesId] = '<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>';
        $objects[$catalogId] = '<< /Type /Catalog /Pages '.$pagesId.' 0 R >>';

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxId + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= 'trailer << /Size '.($maxId + 1).' /Root '.$catalogId." 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
